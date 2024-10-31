<?php
return [
    'debug'=>true,//当前是否为调试模式
    'timezone'=>'Asia/Shanghai',//时区
    'default_route'=>true,//启用默认路由，nova默认根据url自动解析到AnyModule/AnyController/AnyMethod方法，如果设置为false，则需要手动配置路由
    'cache_driver' => 'nova\framework\cache\ApcuCacheDriver',//如果apcu不可用，则默认使用文件缓存
    'domain'=>[
        '0.0.0.0',//允许访问的域名
    ],
    'version'=>'',//版本号
    'versionCode'=>1,//版本号
    'db'=>[
        'type'=>'mysql',
        'host'=>'localhost',
        'port'=>3306,
        'username'=>'root',
        'password'=>'root',
        'db'=>'test',
        'charset'=>'utf8mb4',
    ]
];