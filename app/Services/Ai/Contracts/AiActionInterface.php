<?php

namespace App\Services\Ai\Contracts;

interface AiActionInterface
{
    /**
     * Handle the AI tool execution and return pure structured data.
     *
     * @param array $args Tool arguments passed by Gemini AI
     * @return array Structured data response
     */
    public function handle(array $args): array;
}
