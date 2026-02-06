<?php

declare(strict_types=1);

namespace Marko\AdminApi;

use Marko\Routing\Http\Response;

class ApiResponse
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
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
            statusCode: 200,
        );
    }

    /**
     * @param array<int, array<string, mixed>> $errors
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
            statusCode: 200,
        );
    }

    public static function notFound(
        string $message = 'Not found',
    ): Response {
        return self::error(
            errors: [['message' => $message]],
            statusCode: 404,
        );
    }

    public static function forbidden(
        string $message = 'Forbidden',
    ): Response {
        return self::error(
            errors: [['message' => $message]],
            statusCode: 403,
        );
    }

    public static function unauthorized(
        string $message = 'Unauthorized',
    ): Response {
        return self::error(
            errors: [['message' => $message]],
            statusCode: 401,
        );
    }
}
