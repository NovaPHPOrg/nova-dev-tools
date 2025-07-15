<?php
return [
    'debug'=>true,//当前是否为调试模式
    'timezone'=>'Asia/Shanghai',//时区
    'default_route'=>true,//启用默认路由，nova默认根据url自动解析到AnyModule/AnyController/AnyMethod方法，如果设置为false，则需要手动配置路由
    'domain'=>[
        '0.0.0.0',//允许访问的域名
    ],
    'version'=>'1.0.0',//版本号
];