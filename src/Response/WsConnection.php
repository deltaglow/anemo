<?php

namespace DeltaGlow\Anemo\Response;

use Swoole\Coroutine\Http\Client;
use Swoole\WebSocket\Frame;

class WsConnection extends Client
{
    /**
     * @param string $text
     * @return bool
     */
    public function pushText(string $text): bool
    {
        return $this->push($text, WEBSOCKET_OPCODE_TEXT);
    }

    public function pushBinary(string $data): bool
    {
        return $this->push($data, WEBSOCKET_OPCODE_BINARY);
    }

    public function pushJson(array|object $data): bool
    {
        return $this->push(json_encode($data), WEBSOCKET_OPCODE_TEXT);
    }

    /**
     * Overrides the parent's recv() method to automatically handle PING/PONG frames.
     *
     * @param float $timeout The timeout in seconds.
     * @return Frame|false|string The received frame object, string data (if data=true), or false on failure/timeout.
     */
    public function receive(float $timeout = 0): Frame|false|string
    {
        // Loop until we get a non-control frame or the receive operation fails/times out
        while (true) {
            $frame = parent::recv($timeout);

            if ($frame === false) {
                // Error or timeout occurred. Return false to the caller.
                return false;
            }

            if ($frame instanceof Frame) {
                switch ($frame->opcode) {
                    case WEBSOCKET_OPCODE_PING:
                        // Received a PING. Automatically send a PONG response.
                        // The data payload of the PING is used as the payload for the PONG.
                        $this->push($frame->data, WEBSOCKET_OPCODE_PONG);
                        // Continue the loop to wait for the next frame
                        break;

                    case WEBSOCKET_OPCODE_PONG:
                        // Received a PONG (often a response to our own keep-alive PING).
                        // This is an internal signal; don't return it to the caller.
                        // Continue the loop to wait for the next frame
                        break;

                    case WEBSOCKET_OPCODE_CLOSE:
                        // Received a CLOSE frame.
                        // You might want to handle connection closing logic here.
                        // For now, treat it like an application frame or return it depending on desired behavior.
                        // Returning the frame:
                        return $frame;

                    default:
                        // Text (WEBSOCKET_OPCODE_TEXT), Binary (WEBSOCKET_OPCODE_BINARY), or continuation frames.
                        // These are application data frames. Return them to the caller.
                        return $frame;
                }
            } else {
                // This case is unlikely if the parent method returns Frame|false|string,
                // but if it returned a string (often configured via flags not available here),
                // we'd return it. In standard Swoole usage, it returns a Frame object.
                return $frame;
            }
        }
    }

    public function receiveJson(float $timeout = 0): array|false
    {
        $frame = $this->receive($timeout);
        if ($frame === false) {
            return false;
        }
        return json_decode($frame->data, true);
    }

    public function receiveText(float $timeout = 0): string|false
    {
        $frame = $this->receive($timeout);
        if ($frame === false) {
            return false;
        }
        return $frame->data;
    }
}