<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Bl;

use Hyperf\Rpc\Context;
use Hyperf\Rpc\Contract\DataFormatterInterface;

class GoDataFormatter implements DataFormatterInterface
{
    /**
     * @var Context
     */
    protected $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function formatRequest($data)
    {
        [
            $path,
            $params,
            $id
        ] = $data;
        $path_list = preg_split("/\//", $path, -1, PREG_SPLIT_NO_EMPTY);
        $service_name_list = preg_split("/_/", $path_list[0], -1, PREG_SPLIT_NO_EMPTY);
        $service_name = "";
        array_map(function ($word) use (&$service_name) {
            $service_name .= ucfirst($word);
        }, $service_name_list);
        $path = $service_name . '.' . ucfirst($path_list[1]);
        return [
            'method' => $path,
            'params' => array($params),
            'id'     => $id
        ];
    }

    public function formatResponse($data)
    {
        [
            $id,
            $result
        ] = $data;
        return [
            'jsonrpc' => '2.0',
            'id'      => $id,
            'result'  => $result,
            'error'   => null,
            'context' => $this->context->getData(),
        ];
    }

    public function formatErrorResponse($data)
    {

        [
            $id,
            $code,
            $message,
            $data,
            $protocol_type
        ] = $data;
        if (isset($data) && $data instanceof \Throwable) {
            $data = [
                'class'   => get_class($data),
                'code'    => $data->getCode(),
                'message' => $data->getMessage(),
            ];
        }
        if ($protocol_type == "jsonrpc-bl") {
            return [
                'jsonrpc' => '2.0',
                'id'      => $id ?? null,
                'error'   => "code:{$code} message:{$message}",
                "result"  => $data,
                'context' => $this->context->getData(),
            ];
        } else {
            return [
                'jsonrpc' => '2.0',
                'id'      => $id ?? null,
                'error'   => [
                    'code'    => $code,
                    'message' => $message,
                    'data'    => $data,
                ],
                'context' => $this->context->getData(),
            ];
        }

    }
}
