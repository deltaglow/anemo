<?php

namespace DeltaGlow\Anemo\Exception;

use Swoole\WebSocket\Frame;

class WsJsonNotValid extends WsException
{
    public ?Frame $frame = null;
}