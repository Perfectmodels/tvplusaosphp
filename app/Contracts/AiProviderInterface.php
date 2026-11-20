<?php

namespace App\Contracts;

interface AiProviderInterface
{
    public function chat(string $systemPrompt, string $userPrompt, array $options = []): string;

    public function streamChat(string $systemPrompt, string $userPrompt, callable $onChunk, array $options = []): void;
}
