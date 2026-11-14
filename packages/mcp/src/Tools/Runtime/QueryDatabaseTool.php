<?php

declare(strict_types=1);

namespace Marko\Mcp\Tools\Runtime;

use Marko\Mcp\Tools\Runtime\Adapters\MarkoQueryConnection;
use Marko\Mcp\Tools\ToolDefinition;
use Marko\Mcp\Tools\ToolHandlerInterface;
use Throwable;

/**
 * The query_database MCP tool.
 *
 * Reads are guarded twice. The SQL is checked first: it must be one statement that starts with an allowed keyword
 * and contains no data-modifying keyword or known side-effecting function. It then runs inside a read-only
 * transaction that is always rolled back (MarkoQueryConnection::readOnlyQuery()), so the database refuses any write
 * the check missed.
 *
 * Writes are off unless the server operator enables them with the mcp.database.allow_writes config key
 * (MCP_ALLOW_WRITES). The model-supplied allowWrite and confirm arguments can never enable writes on their own:
 * prompt-injected content reaching the agent could set them too.
 */
readonly class QueryDatabaseTool implements ToolHandlerInterface
{
    private const array ALLOWED_PREFIXES = ['SELECT', 'WITH', 'SHOW', 'EXPLAIN', 'DESCRIBE'];

    /**
     * Keywords a read never needs. INSERT/UPDATE/DELETE/MERGE catch data-modifying CTEs and EXPLAIN ANALYZE <write>;
     * INTO catches SELECT ... INTO OUTFILE/DUMPFILE and SELECT INTO new_table.
     */
    private const string FORBIDDEN_KEYWORDS = '/\b(INSERT|UPDATE|DELETE|MERGE|INTO)\b/i';

    /**
     * Functions with side effects outside the transaction (other connections, server files, sequences, sessions).
     */
    private const string FORBIDDEN_FUNCTIONS = '/\b(dblink\w*|pg_read_file|pg_read_binary_file|pg_ls_dir|pg_stat_file'
        . '|lo_import|lo_export|lo_from_bytea|lo_put|lo_unlink|pg_terminate_backend|pg_cancel_backend|pg_reload_conf'
        . '|pg_rotate_logfile|pg_advisory_\w*|set_config|setval|nextval|load_file|get_lock|sys_exec|sys_eval)\s*\(/i';

    /**
     * Lexical rules of each SQL dialect the check scans the statement under. The statement is rejected if ANY
     * dialect sees a problem, so a quote, comment or escape that one database reads differently from another cannot
     * hide a second statement.
     *
     * @var array<string, array{backslashEscapes: bool, hashComments: bool, dashCommentNeedsSpace: bool, dollarQuotes: bool, identifierQuotes: list<string>}>
     */
    private const array DIALECTS = [
        'mysql' => [
            'backslashEscapes' => true,
            'hashComments' => true,
            'dashCommentNeedsSpace' => true,
            'dollarQuotes' => false,
            'identifierQuotes' => ['`'],
        ],
        'mysql (NO_BACKSLASH_ESCAPES)' => [
            'backslashEscapes' => false,
            'hashComments' => true,
            'dashCommentNeedsSpace' => true,
            'dollarQuotes' => false,
            'identifierQuotes' => ['`'],
        ],
        'pgsql' => [
            'backslashEscapes' => false,
            'hashComments' => false,
            'dashCommentNeedsSpace' => false,
            'dollarQuotes' => true,
            'identifierQuotes' => ['"'],
        ],
        'sqlite' => [
            'backslashEscapes' => false,
            'hashComments' => false,
            'dashCommentNeedsSpace' => false,
            'dollarQuotes' => false,
            'identifierQuotes' => ['"', '`', '['],
        ],
    ];

    public function __construct(
        private MarkoQueryConnection $connection,
        private bool $writesEnabled = false,
    ) {}

    public static function definition(
        MarkoQueryConnection $connection,
        bool $writesEnabled = false,
    ): ToolDefinition {
        $properties = ['sql' => ['type' => 'string', 'description' => 'SQL statement to execute']];

        if ($writesEnabled) {
            $properties['allowWrite'] = ['type' => 'boolean', 'description' => 'Set true to run a write operation'];
            $properties['confirm'] = ['type' => 'boolean', 'description' => 'Set true to confirm the write operation'];
        }

        return new ToolDefinition(
            name: 'query_database',
            description: $writesEnabled
                ? 'Query the database. Read-only by default (single statement, run in a read-only transaction); '
                    . 'set allowWrite+confirm for a write operation.'
                : 'Query the database. Read-only: a single SELECT, WITH, SHOW, EXPLAIN or DESCRIBE statement, run in '
                    . 'a read-only transaction. Writes are disabled by the server config.',
            inputSchema: [
                'type' => 'object',
                'required' => ['sql'],
                'properties' => $properties,
            ],
            handler: new self($connection, $writesEnabled),
        );
    }

    public function handle(
        array $arguments,
    ): array {
        $sql = trim((string) ($arguments['sql'] ?? ''));
        $allowWrite = (bool) ($arguments['allowWrite'] ?? false);
        $confirm = (bool) ($arguments['confirm'] ?? false);

        if ($allowWrite) {
            return $this->handleWrite($sql, $confirm);
        }

        $rejection = $this->readRejection($sql);

        if ($rejection !== null) {
            return $this->error("SQL statement not permitted: $rejection");
        }

        try {
            $rows = $this->connection->readOnlyQuery($sql);
        } catch (Throwable $e) {
            return $this->error('ERROR: ' . $e->getMessage());
        }

        return ['content' => [['type' => 'text', 'text' => $this->formatRows($rows)]]];
    }

    /**
     * @return array{content: list<array{type: string, text: string}>, isError?: bool}
     */
    private function handleWrite(
        string $sql,
        bool $confirm,
    ): array {
        if (! $this->writesEnabled) {
            return $this->error(
                'Write operations are disabled. query_database is read-only unless the server operator sets the '
                . 'mcp.database.allow_writes config key to true (MCP_ALLOW_WRITES=true). The allowWrite and confirm '
                . 'arguments cannot enable writes on their own.',
            );
        }

        if (! $confirm) {
            return $this->error(
                'Write operation requires confirm=true to proceed. Set both allowWrite=true and confirm=true.',
            );
        }

        try {
            $rows = $this->connection->query($sql);
        } catch (Throwable $e) {
            return $this->error('ERROR: ' . $e->getMessage());
        }

        $text = "WARNING: WRITE OPERATION executed.\n\n" . $this->formatRows($rows);

        return ['content' => [['type' => 'text', 'text' => $text]]];
    }

    /**
     * Why a read is not permitted, or null when every dialect accepts it.
     */
    private function readRejection(
        string $sql,
    ): ?string {
        foreach (self::DIALECTS as $name => $dialect) {
            $code = $this->stripLiteralsAndComments($sql, $dialect);

            if ($code === null) {
                return "it has an unterminated string, identifier or comment, or a MySQL executable comment (/*! */) (as read by $name).";
            }

            $rejection = $this->codeRejection($code);

            if ($rejection !== null) {
                return "$rejection (as read by $name).";
            }
        }

        return null;
    }

    private function codeRejection(
        string $code,
    ): ?string {
        $code = trim($code);

        if (
            preg_match('/^[A-Za-z]+/', $code, $match) !== 1
            || ! in_array(strtoupper($match[0]), self::ALLOWED_PREFIXES, strict: true)
        ) {
            return 'only a single SELECT, WITH, SHOW, EXPLAIN or DESCRIBE statement is allowed';
        }

        // One trailing semicolon is allowed; any other one separates statements.
        if (str_contains((string) preg_replace('/;\s*$/', '', $code), ';')) {
            return 'only one statement is allowed; a semicolon outside a string literal separates statements';
        }

        if (preg_match(self::FORBIDDEN_KEYWORDS, $code, $match) === 1) {
            return "the keyword {$match[1]} is not allowed in a read (data-modifying CTEs, EXPLAIN ANALYZE of a write and SELECT ... INTO are rejected)";
        }

        if (preg_match(self::FORBIDDEN_FUNCTIONS, $code, $match) === 1) {
            return "the function {$match[1]}() has side effects and is not allowed in a read";
        }

        return null;
    }

    /**
     * The statement with comments removed and string literals blanked, as the given dialect lexes it. Quoted
     * identifiers keep their name (unquoted) so a quoted function name is still checked. Null when a literal,
     * identifier or comment is unterminated, or for a MySQL executable comment, whose content runs as SQL.
     *
     * @param array{backslashEscapes: bool, hashComments: bool, dashCommentNeedsSpace: bool, dollarQuotes: bool, identifierQuotes: list<string>} $dialect
     */
    private function stripLiteralsAndComments(
        string $sql,
        array $dialect,
    ): ?string {
        $code = '';
        $length = strlen($sql);
        $i = 0;

        while ($i < $length) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if (
                $char === '/'
                && $next === '*'
            ) {
                $end = strpos($sql, '*/', $i + 2);

                if (
                    $end === false
                    || ($sql[$i + 2] ?? '') === '!'
                ) {
                    return null;
                }

                $code .= ' ';
                $i = $end + 2;

                continue;
            }

            // MySQL only starts a -- comment when whitespace or a control character follows: --1 is minus minus 1.
            $afterDashes = $sql[$i + 2] ?? "\n";
            $isDashComment = $char === '-'
                && $next === '-'
                && (
                    ! $dialect['dashCommentNeedsSpace']
                    || ctype_space($afterDashes)
                    || ctype_cntrl($afterDashes)
                );

            if (
                $isDashComment
                || (
                    $char === '#'
                    && $dialect['hashComments']
                )
            ) {
                $end = strpos($sql, "\n", $i);
                $code .= ' ';
                $i = $end === false ? $length : $end + 1;

                continue;
            }

            if (
                $char === "'"
                || (
                    $char === '"'
                    && ! in_array('"', $dialect['identifierQuotes'], true)
                )
            ) {
                $end = $this->closingQuote($sql, $i, $char, $dialect['backslashEscapes']);

                if ($end === null) {
                    return null;
                }

                $code .= ' ';
                $i = $end + 1;

                continue;
            }

            if (in_array($char, $dialect['identifierQuotes'], true)) {
                $close = $char === '[' ? ']' : $char;
                $end = $this->closingQuote($sql, $i, $close, false);

                if ($end === null) {
                    return null;
                }

                $code .= ' ' . str_replace($close . $close, $close, substr($sql, $i + 1, $end - $i - 1)) . ' ';
                $i = $end + 1;

                continue;
            }

            if (
                $char === '$'
                && $dialect['dollarQuotes']
                && (
                    $i === 0
                    || preg_match('/[A-Za-z0-9_$]/', $sql[$i - 1]) !== 1
                )
                && preg_match('/\G\$([A-Za-z_][A-Za-z0-9_]*)?\$/', $sql, $match, 0, $i) === 1
            ) {
                $end = strpos($sql, $match[0], $i + strlen($match[0]));

                if ($end === false) {
                    return null;
                }

                $code .= ' ';
                $i = $end + strlen($match[0]);

                continue;
            }

            $code .= $char;
            $i++;
        }

        return $code;
    }

    /**
     * Position of the quote that closes the literal or identifier opened at $start, or null when unterminated.
     * A doubled quote is an escaped quote; so is a backslash-escaped one when the dialect uses backslash escapes.
     */
    private function closingQuote(
        string $sql,
        int $start,
        string $quote,
        bool $backslashEscapes,
    ): ?int {
        $length = strlen($sql);

        for ($i = $start + 1; $i < $length; $i++) {
            if (
                $backslashEscapes
                && $sql[$i] === '\\'
            ) {
                $i++;

                continue;
            }

            if ($sql[$i] === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) {
                    $i++;

                    continue;
                }

                return $i;
            }
        }

        return null;
    }

    /**
     * @return array{content: list<array{type: string, text: string}>, isError: bool}
     */
    private function error(
        string $message,
    ): array {
        return ['content' => [['type' => 'text', 'text' => $message]], 'isError' => true];
    }

    /** @param list<array<string, mixed>> $rows */
    private function formatRows(
        array $rows,
    ): string {
        if ($rows === []) {
            return '(no rows returned)';
        }

        return implode("\n", array_map(
            fn (array $row) => implode(', ', array_map(
                fn (string $k, mixed $v) => "$k: $v",
                array_keys($row),
                array_values($row),
            )),
            $rows,
        ));
    }
}
