<?php

namespace DeltaGlow\Anemo\Response;

use DeltaGlow\Anemo\Exception\WsFalseFrame;
use DeltaGlow\Anemo\Exception\WsJsonNotValid;
use DeltaGlow\Anemo\Exception\WsConnectionClosed;
use Swoole\Coroutine;
use Swoole\Coroutine\Http\Client;
use Swoole\WebSocket\Frame;

class WsConnection extends Client
{
    private int $autoping_cid;
    protected \Closure $event_close_handler;
    private bool $closed = false;

    public function startAutoping(int $interval, null|\Closure $data_callback): void
    {
        $this->stopAutoping();

        $this->autoping_cid = Coroutine::create(function () use ($interval, $data_callback) {
            while(true) {
                Coroutine::sleep($interval);
                $data = null;
                if($data_callback instanceof \Closure) {
                    try {
                        $data = call_user_func($data_callback);
                    } catch (\Throwable) {
                        continue;
                    }
                }

                if (!$this->ping($data)) {
                    break;
                }
            }
        });
    }

    public function stopAutoping(): void
    {
        if(!isset($this->autoping_cid)) {
            return;
        }
        Coroutine::cancel($this->autoping_cid);
        unset($this->autoping_cid);
    }

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
        return $this->push(json_encode($data, JSON_THROW_ON_ERROR), WEBSOCKET_OPCODE_TEXT);
    }

    public function ping(string|array|null $data = null): bool
    {
        if(is_array($data)) {
            $data = json_encode($data, JSON_THROW_ON_ERROR);
        }
        return $this->push($data ?? '', WEBSOCKET_OPCODE_PING);
    }

    public function pong(string|array|null $data = null): bool
    {
        if(is_array($data)) {
            $data = json_encode($data, JSON_THROW_ON_ERROR);
        }
        return $this->push($data ?? '', WEBSOCKET_OPCODE_PONG);
    }

    /**
     * Overrides the parent's recv() method to automatically handle PING/PONG frames.
     *
     * @param float $timeout The timeout in seconds.
     * @return Frame|false|string The received frame object, string data (if data=true), or false on failure/timeout.
     */
    public function receive(float $timeout = 0): Frame|false|string
    {
        $deadlineNs = $timeout > 0 ? hrtime(true) + (int) ($timeout * 1_000_000_000) : null;

        // Loop until we get a non-control frame or the receive operation fails/times out
        while (true) {
            $remaining = $timeout;
            if ($deadlineNs !== null) {
                $remaining = ($deadlineNs - hrtime(true)) / 1_000_000_000;
                if ($remaining <= 0.0) {
                    return false;
                }
            }

            $frame = parent::recv($timeout);

            if ($frame === false) {
                // Error or timeout occurred. Return false to the caller.
                $this->executeCloseEvent();
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
                        $this->executeCloseEvent();
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
                $this->executeCloseEvent();
                return $frame;
            }
        }
    }

    private function executeCloseEvent(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;

        $this->stopAutoping();
        if(isset($this->event_close_handler)) {
            ($this->event_close_handler)($this);
        }
    }

    private function assertDataFrame(Frame|false|string $frame): Frame
    {
        if ($frame === false) {
            throw new WsFalseFrame();
        }

        if ($frame === '') {
            throw new WsConnectionClosed('Peer closed the connection.');
        }

        if ($frame->opcode === WEBSOCKET_OPCODE_CLOSE) {
            throw new WsConnectionClosed('Received a WebSocket CLOSE frame.');
        }

        return $frame;
    }

    public function receiveJson(float $timeout = 0): array|null
    {
        $frame = $this->assertDataFrame($this->receive($timeout));

        if (!json_validate($frame->data)) {
            $exception = new WsJsonNotValid('Frame data is not valid JSON string.');
            $exception->frame = $frame;
            throw $exception;
        }
        return json_decode($frame->data, true);
    }

    public function receiveText(float $timeout = 0): string
    {
        return $this->assertDataFrame($this->receive($timeout))->data;
    }

    public function close(): bool
    {
        $this->stopAutoping();
        return parent::close();
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function onClose(callable $callback): self
    {
        $this->event_close_handler = \Closure::fromCallable($callback);
        return $this;
    }
}