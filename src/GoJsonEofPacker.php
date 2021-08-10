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

use Hyperf\Contract\PackerInterface;

class GoJsonEofPacker implements PackerInterface
{
    /**
     * @var string
     */
    protected $eof;

    protected $eof_list = [
        'hyperf-json-rpc'=>"\r\n",
        'go-json-rpc'=>"\n",
    ];
    public function __construct(array $options = [])
    {
        $this->eof = $options['settings']['package_eof'] ?? "\r\n";
    }

    public function pack($data): string
    {

        if(isset($data['protocol_type']) && isset($this->eof_list[$data['protocol_type']])){
            $eof = $this->eof_list[$data['protocol_type']];
        }else{
            $eof =   $this->eof;
        }
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $data .$eof;
    }

    public function unpack(string $data)
    {
        $data = rtrim($data, $this->eof);
        $data = json_decode($data, true);
        if (isset($data['method']) && strpos($data['method'], '.')) { //兼容go jsonrpc协议
            $class_method_list = explode(".", $data['method']);
            $method = "";
            foreach ($class_method_list as $value) {
                $method .= '/' . strtolower(preg_replace('/(?<=[a-z])([A-Z])/', '_$1', $value));
            }
            $data['method'] = $method;
            if (isset($data['params'][0])) {
                $data['params'] = array_values($data['params'][0]);
            }
        }
        return $data;
    }
}
