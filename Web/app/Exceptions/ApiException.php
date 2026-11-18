<?php

namespace App\Exceptions;

use Exception;

/**
 * 5章「エラーレスポンス共通形式」: {"error": "コード", "message": "人間可読メッセージ"}
 * をそのまま表現するための例外。コントローラ/サービスはこれをthrowするだけでよい。
 */
class ApiException extends Exception
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function render()
    {
        return response()->json([
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
