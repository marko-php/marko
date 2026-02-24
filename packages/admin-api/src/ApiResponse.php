<?php

declare(strict_types=1);

namespace Marko\AdminApi;

use JsonException;
use Marko\Routing\Http\Response;

class ApiResponse
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @throws JsonException
     */
    public static function success(
        array $data = [],
        array $meta = [],
    ): Response {
        return Response::json(
            data: [
                'data' => $data,
                'meta' => $meta,
            ],
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     * @throws JsonException
     */
    public static function created(
        array $data = [],
        array $meta = [],
    ): Response {
        return Response::json(
            data: [
                'data' => $data,
                'meta' => $meta,
            ],
            statusCode: 201,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $errors
     * @throws JsonException
     */
    public static function error(
        array $errors,
        int $statusCode = 400,
    ): Response {
        return Response::json(
            data: [
                'errors' => $errors,
            ],
            statusCode: $statusCode,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $data
     * @throws JsonException
     */
    public static function paginated(
        array $data,
        int $page,
        int $perPage,
        int $total,
    ): Response {
        return Response::json(
            data: [
                'data' => $data,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => (int) ceil($total / $perPage),
                ],
            ],
        );
    }

    /**
     * @throws JsonException
     */
    public static function notFound(
        string $message = 'Not found',
    ): Response {
        return self::error(
            errors: [['message' => $message]],
            statusCode: 404,
        );
    }

    /**
     * @throws JsonException
     */
    public static function forbidden(
        string $message = 'Forbidden',
    ): Response {
        return self::error(
            errors: [['message' => $message]],
            statusCode: 403,
        );
    }

    /**
     * @throws JsonException
     */
    public static function unauthorized(
        string $message = 'Unauthorized',
    ): Response {
        return self::error(
            errors: [['message' => $message]],
            statusCode: 401,
        );
    }
}
