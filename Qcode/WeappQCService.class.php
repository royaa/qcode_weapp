<?php
namespace Qcode;
use Qcode\Constants;
use Qcode\Tools;

class WeappQCService {
	const API_BASE_URL = 'https://api.weixin.qq.com';
	const WX_QC_ACODE = '/wxa/getwxacode';
	const WX_QC_ACODE_UNLIMIT = '/wxa/getwxacodeunlimit';
	const WX_CGI_WXAQRCODE = '/cgi-bin/wxaapp/createwxaqrcode';
	const TOKEN_API = 'https://api.weixin.qq.com/cgi-bin/token?';

	public $token;
	public $encodingAesKey;
	public $appid;
	public $appsecret;
	public $access_token;
	public $tool;
	public $imgpath;

	/**
	 * @param $wechatConfig array, 微信开放平台或者公众号的配置信息
	 * @param $weappConfig array, 微信小程序的配置信息
	 */
	public function __construct($wechatConfig) {
		$this->token = $wechatConfig['token'];
		$this->appid = $wechatConfig['appid'];
		$this->appsecret = $wechatConfig['appsecret'];
		$this->encodingAesKey = $wechatConfig['encodingaeskey'];
		$this->imgpath = $wechatConfig['imgpath'];
		$this->access_token = self::getMPAccessToken($wechatConfig['appid'], $wechatConfig['appsecret']);
	}
	
	/**
	 * 获取开放平台的token信息
	 */
	public static function getMPAccessToken($appId, $appSecret) {
		//TODO,最好加上缓存.该token由微信10分钟推送一次

		$requestParams = [
            'appid' => $appId,
            'secret' => $appSecret,
            'grant_type' => 'client_credential'
        ];

		$res = json_decode(Tools::httpGet(self::TOKEN_API . http_build_query($requestParams)),true);
		$accessToken = isset($body['access_token']) ? $body['access_token'] : '';
		
        return $accessToken ;
	}


	/**
	 * 适用于需要的码数量较少的业务场景
	 * @param $path string, 不能为空，最大长度 128 字节,是业务链接
	 * @param $width int, 二维码的宽度
	 * @param $autoColor boolean, 自动配置线条颜色，如果颜色依然是黑色，则说明不建议配置主色调
	 * @param $lineColor object,auth_color 为 false 时生效，使用 rgb 设置颜色 例如 {"r":"xxx","g":"xxx","b":"xxx"},十进制表示
	 *
	 * @return 
	 */
	public function getwxacode($path, $width = 430) {
		$requestParams = array(
			'path' => $path,
			'width' => $width,
		);

		$url = self::API_BASE_URL . self::WX_QC_ACODE .  '?access_token=' . $this->access_token;

		return $this->_getQcImg($url, $requestParams);
	}

	/**
	 * 适用于需要的码数量极多，或仅临时使用的业务场景
	 * @param string $scene,最大32个可见字符，只支持数字，大小写英文以及部分特殊字符：!#$&'()*+,/:;=?@-._~，其它字符请自行编码为合法字符（因不支持%，中文无法使用 urlencode 处理，请使用其他编码方式）
	 * @param string $page,必须是已经发布的小程序存在的页面（否则报错），例如 "pages/index/index" ,根路径前不要填加'/',不能携带参数（参数请放在scene字段里），如果不填写这个字段，默认跳主页面
	 * @param int $width,二维码的宽度
	 * @param bool $autoColor,自动配置线条颜色，如果颜色依然是黑色，则说明不建议配置主色调
	 * @param object $lineColor,auto_color 为 false 时生效，使用 rgb 设置颜色 例如 {"r":"xxx","g":"xxx","b":"xxx"} 十进制表示
	 */
	public function getwxacodeunlimit($scene, $page, $width = 430) {
		$requestParams = array(
			'scene' => $scene,
			'page' => $page,
			'width' => $width,
		);

		$url = self::API_BASE_URL . self::WX_QC_ACODE_UNLIMIT . '?access_token=' . $this->access_token;
		return $this->_getQcImg($url, $requestParams);
	}

	/**
	 * 适用于需要的码数量较少的业务场景
	 * @param string $path, 不能为空，最大长度 128 字节
	 * @param int $width, 二维码的宽度
	 */
	public function createwxaqrcode($path, $width = 430) {
		$requestParams = array(
			'path' => $path,
			'width' => $width,
		);
		
		$url = self::API_BASE_URL . self::WX_CGI_WXAQRCODE .  '?access_token=' . $this->access_token;
		return $this->_getQcImg($url, $requestParams);
	}

	private function _getQcImg($url, $params) {
		$img = Tools::httpPost($url, json_encode($params));

		if (strlen($img) < 1000) {
			$decode = json_decode($img, true);
			return array('code' => $decode['errcode'], 'msg' => $decode['errmsg'],'path' => '');
		}

		$path = $this->writeQcPath($img);

		return array(
			'path' => $path,
			'code' => 0,
			'msg' => ''
		);
	}
	
	/**
	 * 将二进制数据流写入文件
	 * @param string $stream
	 * @return boolean
	 */
	public function writeQcPath($stream) {
		$subPath = date('Y/m');
		$savePath = $this->imgpath . $subPath;
        // 检查上传目录
        if(!is_dir($savePath)) {
            // 检查目录是否编码后的
            if(is_dir(base64_decode($savePath))) {
                $savePath	=	base64_decode($savePath);
            }else{
                // 尝试创建目录
                if(!mkdir($savePath, 0755, true)){
                    $this->error  =  '上传目录'.$savePath.'不存在';
                    return false;
                }
            }
        }else {
            if(!is_writeable($savePath)) {
                $this->error  =  '上传目录'.$savePath.'不可写';
                return false;
            }
        }

		$filename = uniqid() . '.png';

		$realpath = $savePath . '/' . $filename;
		$file = fopen($realpath,"w");//打开文件准备写入
		fwrite($file, $stream);//写入
		fclose($file);//关闭

		return $subPath . '/' . $filename;
	}

}