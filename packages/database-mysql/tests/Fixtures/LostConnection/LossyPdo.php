<?php

declare(strict_types=1);

namespace Marko\Database\MySql\Tests\Fixtures\LostConnection;

use Marko\Database\MySql\Tests\Fixtures\Retry\ScriptedPdo;
use PDO;
use PDOException;
use PDOStatement;

/**
 * A ScriptedPdo whose prepare() and prepared statements' execute() can be
 * scripted to fail, so a lost or failed statement can be simulated without a
 * server.
 */
class LossyPdo extends ScriptedPdo
{
    /**
     * Failures thrown by the next prepare() calls, in order.
     *
     * @var list<PDOException>
     */
    public array $prepareFailures = [];

    /**
     * Failures thrown by the next execute() calls of statements prepared on this PDO, in order.
     *
     * @var list<PDOException>
     */
    public array $executeFailures = [];

    /**
     * Failures thrown by the next rollBack() calls, in order.
     *
     * @var list<PDOException>
     */
    public array $rollbackFailures = [];

    /**
     * Failures thrown by the next beginTransaction() calls, in order.
     *
     * @var list<PDOException>
     */
    public array $beginFailures = [];

    public int $prepareCalls = 0;

    public function __construct()
    {
        parent::__construct();
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, [LossyStatement::class, [$this]]);
    }

    public function prepare(
        string $query,
        array $options = [],
    ): PDOStatement|false {
        $this->prepareCalls++;
        $failure = array_shift($this->prepareFailures);

        if ($failure !== null) {
            throw $failure;
        }

        return parent::prepare($query, $options);
    }

    public function beginTransaction(): bool
    {
        $failure = array_shift($this->beginFailures);

        if ($failure !== null) {
            throw $failure;
        }

        return parent::beginTransaction();
    }

    public function rollBack(): bool
    {
        $failure = array_shift($this->rollbackFailures);

        if ($failure !== null) {
            $this->statements[] = 'ROLLBACK';

            throw $failure;
        }

        return parent::rollBack();
    }

    public static function lostConnection(): PDOException
    {
        return self::failure('HY000', 2006, 'MySQL server has gone away');
    }

    public static function syntaxError(): PDOException
    {
        return self::failure('42000', 1064, 'You have an error in your SQL syntax near');
    }

    private static function failure(
        string $sqlState,
        int $driverCode,
        string $message,
    ): PDOException {
        $exception = new PDOException("SQLSTATE[$sqlState]: $driverCode $message");
        $exception->errorInfo = [$sqlState, $driverCode, $message];

        return $exception;
    }
}
