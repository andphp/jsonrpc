# hyperf rpc与go的 josnrpc库通讯
###兼容hyperf框架的之间jsonrpc通讯 以及与golang的jsonrpc库通讯

###config/autoload/servers.php配置文件修改

1. 协议配置项 protocol为jsonrpc-bl
2. 分包规则package_eof 为 \n
```php
<?php
return [
    // 此处省略了其它同层级的配置
    'consumers' => [
        [
            // name 需与服务提供者的 name 属性相同
            'name' => 'CalculatorService',
            // 服务接口名，可选，默认值等于 name 配置的值，如果 name 直接定义为接口类则可忽略此行配置，如 name 为字符串则需要配置 service 对应到接口类
            'service' => \App\JsonRpc\CalculatorServiceInterface::class,
            // 对应容器对象 ID，可选，默认值等于 service 配置的值，用来定义依赖注入的 key
            'id' => \App\JsonRpc\CalculatorServiceInterface::class,
            // 服务提供者的服务协议，可选，默认值为 jsonrpc-http
            // 可选 jsonrpc-http jsonrpc jsonrpc-tcp-length-check jsonrpc-bl
            'protocol' => 'jsonrpc-bl',
            // 负载均衡算法，可选，默认值为 random
            'load_balancer' => 'random',
            // 这个消费者要从哪个服务中心获取节点信息，如不配置则不会从服务中心获取节点信息
            'registry' => [
                'protocol' => 'consul',
                'address' => 'http://127.0.0.1:8500',
            ],
            // 如果没有指定上面的 registry 配置，即为直接对指定的节点进行消费，通过下面的 nodes 参数来配置服务提供者的节点信息
            'nodes' => [
                ['host' => '127.0.0.1', 'port' => 9504],
            ],
            // 配置项，会影响到 Packer 和 Transporter
            'options' => [
                'connect_timeout' => 5.0,
                'recv_timeout' => 5.0,
                'settings' => [
                    // 根据协议不同，区分配置
                    'open_eof_split' => true,
                    //拆包规则为\n
                    'package_eof' => "\n",
                ],
                // 重试次数，默认值为 2，收包超时不进行重试。暂只支持 JsonRpcPoolTransporter
                'retry_count' => 2,
                // 重试间隔，毫秒
                'retry_interval' => 100,
                // 当使用 JsonRpcPoolTransporter 时会用到以下配置
                'pool' => [
                    'min_connections' => 1,
                    'max_connections' => 32,
                    'connect_timeout' => 10.0,
                    'wait_timeout' => 3.0,
                    'heartbeat' => -1,
                    'max_idle_time' => 60.0,
                ],
            ],
        ]
    ],
];
```
###服务端config/autoload/server.php配置文件修改项
1. 分包规则package_eof修改为  \n
2. callback数组修改为[
                                \Hyperf\Server\Event::ON_RECEIVE => [\Bl\BlTcpServer::class, 'onReceive'],
                            ]

```php
        [
            'name'      => 'jsonrpc',
            'type'      => Server::SERVER_BASE,
            'host'      => '0.0.0.0',
            'port'      => 9503,
            'sock_type' => SWOOLE_SOCK_TCP,
            'callbacks' => [
                \Hyperf\Server\Event::ON_RECEIVE => [\Bl\BlTcpServer::class, 'onReceive'],
            ],
            'settings'  => [
                'open_eof_split'     => true,
                'package_eof'        => "\n",
                'package_max_length' => 1024 * 1024 * 2,
            ],
        ],
```
###新增事件
新增文件RpcProtocolListener.php到app/Listener文件夹
```php
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

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\BootApplication;
use Hyperf\JsonRpc\PathGenerator;
use Hyperf\Rpc\ProtocolManager;

/**
 * @Listener
 */
class RpcProtocolListener implements ListenerInterface
{
    /**
     * @var ProtocolManager
     */
    private $protocolManager;

    public function __construct(ProtocolManager $protocolManager)
    {
        $this->protocolManager = $protocolManager;
    }

    public function listen(): array
    {
        return [
            BootApplication::class
        ];
    }

    /**
     * All official rpc protocols should register in here,
     * and the others non-official protocols should register in their own component via listener.
     */
    public function process(object $event)
    {
        $this->protocolManager->register('jsonrpc-bl', [
            'packer' => \Bl\GoJsonEofPacker::class,
            'transporter' => \Bl\GoJsonRpcTransporter::class,
            'path-generator' => PathGenerator::class,
            'data-formatter' => \Bl\GoDataFormatter::class,
        ]);
    }
}

```
###通过配置文件注册监听器
修改config/autoload/listeners.php 配置文件
```php
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
return [
    \App\Listener\RpcProtocolListener::class
];

```