<?php

namespace DiscordConnect\Api;

use RuntimeException;

class DiscordApiException extends RuntimeException
{
    /** @var array|null decoded JSON body of the error response, if there was one */
    protected $responseData;

    public function setResponseData(?array $responseData): self
    {
        $this->responseData = $responseData;

        return $this;
    }

    public function getResponseData(): ?array
    {
        return $this->responseData;
    }

    /**
     * The JSON error code of Discord, see https://discord.com/developers/docs/topics/opcodes-and-status-codes#json
     */
    public function getDiscordErrorCode(): ?int
    {
        return isset($this->responseData['code']) ? (int) $this->responseData['code'] : null;
    }
}
