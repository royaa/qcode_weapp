# qcode_weapp
-文档说明：
该api是用于小程序的二维码生成方式，包含了三个接口，具体实现参见程序注释。
-调用方式:
$qcode = new WeappQCService($config);
$pathInfo = $qcode->getwxacode($path);
$path = $pathInfo['path']; //相对路径.
