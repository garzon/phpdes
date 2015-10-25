<?php

define('DOMAIN', 'http://garzon.science/midiPianoOnline');

trait BitwiseOperation {
	public function getBitMask($bit) {
		return 1 << $bit;
	}

	public function getBit($src, $bit) {
		return ($src & $this->getBitMask($bit)) ? 1 : 0;
	}

	public function rol($num, $bits) {
		return $this->getBit($num, $bits-1) | (($num & ($this->getBitMask($bits-1)-1)) << 1);
	}

	public function ror($num, $bits) {
		return ($this->getBit($num, 0) << ($bits-1)) | $this->shr($num, 1);
	}

	// flip nbits in the $num
	public function flip($num, $bits) {
		$ret = 0;
		for($i = 0; $i < $bits; $i++) {
			$ret |= $this->getBit($num, $bits-1-$i) << $i;
		}
		return $ret;
	}

	// logical right shift
	public function shr($num, $bits) {
		if($bits === 0) return $num;
		if($num >= 0) return $num >> $bits;
		if($bits >= PHP_INT_SIZE*8) return 0;
		$num &= PHP_INT_MAX;
		return ($num >> $bits) | $this->getBitMask(PHP_INT_SIZE*8-1-$bits);
	}
}

class DesModel {
	use BitwiseOperation;

	private $subKeys;

	const ROUND_NUM = 16;

	private $subkey_ipc = [
		56, 48, 40, 32, 24, 16,  8,
		0, 57, 49, 41, 33, 25, 17,
		9,  1, 58, 50, 42, 34, 26,
		18, 10,  2, 59, 51, 43, 35,
		62, 54, 46, 38, 30, 22, 14,
		6, 61, 53, 45, 37, 29, 21,
		13,  5, 60, 52, 44, 36, 28,
		20, 12,  4, 27, 19, 11,  3
	];

	private $subkey_pc = [
		13, 16, 10, 23, 0, 4, 2, 27,
		14, 5, 20, 9, 22, 18, 11, 3,
		25, 7, 15, 6, 26, 19, 12, 1,
		40, 51, 30, 36, 46, 54, 29, 39,
		50, 44, 32, 47, 43, 48, 38, 55,
		33, 52, 45, 41, 49, 35, 28, 31,
	];

	private $cipher_ip = [
		57, 49, 41, 33, 25, 17, 9,  1,
		59, 51, 43, 35, 27, 19, 11, 3,
		61, 53, 45, 37, 29, 21, 13, 5,
		63, 55, 47, 39, 31, 23, 15, 7,
		56, 48, 40, 32, 24, 16, 8,  0,
		58, 50, 42, 34, 26, 18, 10, 2,
		60, 52, 44, 36, 28, 20, 12, 4,
		62, 54, 46, 38, 30, 22, 14, 6
	];

	private $cipher_inv_ip = [
		39,  7, 47, 15, 55, 23, 63, 31,
		38,  6, 46, 14, 54, 22, 62, 30,
		37,  5, 45, 13, 53, 21, 61, 29,
		36,  4, 44, 12, 52, 20, 60, 28,
		35,  3, 43, 11, 51, 19, 59, 27,
		34,  2, 42, 10, 50, 18, 58, 26,
		33,  1, 41,  9, 49, 17, 57, 25,
		32,  0, 40,  8, 48, 16, 56, 24
	];

	private $round_ext = [
		31,  0,  1,  2,  3,  4,
		3,  4,  5,  6,  7,  8,
		7,  8,  9, 10, 11, 12,
		11, 12, 13, 14, 15, 16,
		15, 16, 17, 18, 19, 20,
		19, 20, 21, 22, 23, 24,
		23, 24, 25, 26, 27, 28,
		27, 28, 29, 30, 31,  0
	];

	private $sBoxes = [[
		14,4,13,1,2,15,11,8,3,10,6,12,5,9,0,7,
		0,15,7,4,14,2,13,1,10,6,12,11,9,5,3,8,
		4,1,14,8,13,6,2,11,15,12,9,7,3,10,5,0,
		15,12,8,2,4,9,1,7,5,11,3,14,10,0,6,13,
	],[
		15,1,8,14,6,11,3,4,9,7,2,13,12,0,5,10,
		3,13,4,7,15,2,8,14,12,0,1,10,6,9,11,5,
		0,14,7,11,10,4,13,1,5,8,12,6,9,3,2,15,
		13,8,10,1,3,15,4,2,11,6,7,12,0,5,14,9,
	],[
		10,0,9,14,6,3,15,5,1,13,12,7,11,4,2,8,
		13,7,0,9,3,4,6,10,2,8,5,14,12,11,15,1,
		13,6,4,9,8,15,3,0,11,1,2,12,5,10,14,7,
		1,10,13,0,6,9,8,7,4,15,14,3,11,5,2,12,
	],[
		7,13,14,3,0,6,9,10,1,2,8,5,11,12,4,15,
		13,8,11,5,6,15,0,3,4,7,2,12,1,10,14,9,
		10,6,9,0,12,11,7,13,15,1,3,14,5,2,8,4,
		3,15,0,6,10,1,13,8,9,4,5,11,12,7,2,14,
	],[
		2,12,4,1,7,10,11,6,8,5,3,15,13,0,14,9,
		14,11,2,12,4,7,13,1,5,0,15,10,3,9,8,6,
		4,2,1,11,10,13,7,8,15,9,12,5,6,3,0,14,
		11,8,12,7,1,14,2,13,6,15,0,9,10,4,5,3,
	],[
		12,1,10,15,9,2,6,8,0,13,3,4,14,7,5,11,
		10,15,4,2,7,12,9,5,6,1,13,14,0,11,3,8,
		9,14,15,5,2,8,12,3,7,0,4,10,1,13,11,6,
		4,3,2,12,9,5,15,10,11,14,1,7,6,0,8,13,
	],[
		4,11,2,14,15,0,8,13,3,12,9,7,5,10,6,1,
		13,0,11,7,4,9,1,10,14,3,5,12,2,15,8,6,
		1,4,11,13,12,3,7,14,10,15,6,8,0,5,9,2,
		6,11,13,8,1,4,10,7,9,5,0,15,14,2,3,12,
	],[
		13,2,8,4,6,15,11,1,10,9,3,14,5,0,12,7,
		1,15,13,8,10,3,7,4,12,5,6,11,0,14,9,2,
		7,11,4,1,9,12,14,2,0,6,10,13,15,3,5,8,
		2,1,14,7,4,10,8,13,15,12,9,0,3,5,6,11,
	]];

	private $round_p = [
		15, 6, 19, 20, 28, 11, 27, 16, 0, 14, 22, 25, 4, 17, 30, 9,
		1, 7, 23, 13, 31, 26, 2, 8, 18, 12, 29, 5, 21, 10, 3, 24,
	];

	protected function generateSubKeys($key) {
		$key = $this->substitute($key, $this->subkey_ipc);

		$this->subKeys = [];

		$lkey = $key & ($this->getBitMask(28)-1);
		$rkey = $key >> 28;

		for($roundId = 1; $roundId <= 16; $roundId++) {
			if(!in_array($roundId, [1, 2, 9, 16])) {
				$lkey = $this->ror($lkey, 28);
				$rkey = $this->ror($rkey, 28);
			}
			$lkey = $this->ror($lkey, 28);
			$rkey = $this->ror($rkey, 28);

			$this->subKeys[$roundId] = $this->substitute(($rkey << 28) | $lkey, $this->subkey_pc);
		}
	}

	protected function encryptBlock($block) {
		$block = $this->substitute($block, $this->cipher_ip);
		$l = $block & ($this->getBitMask(32)-1);
		$r = $this->shr($block, 32);
		for($i = 1; $i <= self::ROUND_NUM; $i++) {
			$f = $this->round_f($r, $this->subKeys[$i]);
			$xor = $l ^ $f;
			$l = $r;
			$r = $xor;
		}
		$block = ($l << 32) | $r;

		return $this->substitute($block, $this->cipher_inv_ip);
	}

	protected function decryptBlock($block) {
		$block = $this->substitute($block, $this->cipher_ip);

		$l = $this->shr($block, 32);
		$r = $block & ($this->getBitMask(32)-1);
		for($i = self::ROUND_NUM; $i >= 1; $i--) {
			$xor = $r;
			$r = $l;
			$f = $this->round_f($l, $this->subKeys[$i]);
			$l = $xor ^ $f;
		}
		$block = ($r << 32) | $l;

		return $this->substitute($block, $this->cipher_inv_ip);
	}

	private function substitute($block, $subMat) {
		$ret = 0;
		foreach($subMat as $idx => $bit) {
			$ret |= $this->getBit($block, $bit) << $idx;
		}
		return $ret;
	}

	private function s_transform($block, $sbox) {
		$l = ($this->getBit($block, 5) | ($this->getBit($block, 0) << 1)) << 4;
		$n = ($this->getBit($block, 1) << 3) | ($this->getBit($block, 2) << 2) | ($this->getBit($block, 3) << 1) | ($this->getBit($block, 4));
		return $this->flip($sbox[$l + $n], 4);
	}

	private function round_f($block, $subKey) {
		$block = $this->substitute($block, $this->round_ext);
		$block ^= $subKey;
		$s = [];
		$s []= $block & 0x3F;
		$s []= ($block >> 6) & 0x3F;
		$s []= ($block >> 12) & 0x3F;
		$s []= ($block >> 18) & 0x3F;
		$s []= ($block >> 24) & 0x3F;
		$s []= ($block >> 30) & 0x3F;
		$s []= ($block >> 36) & 0x3F;
		$s []= ($block >> 42) & 0x3F;
		$ret = 0;
		foreach($s as $idx => $smallBlock) {
			$ret |= $this->s_transform($smallBlock, $this->sBoxes[$idx]) << ($idx << 2);
		}
		$ret = $this->substitute($ret, $this->round_p);
		return $ret;
	}
}

class Des extends DesModel {
	public function stringToNum($s) {
		$s = array_map(function($chr) { return ord($chr); }, str_split($s));
		$s_int = 0;
		foreach($s as $idx => $val) {
			$s_int |= $this->flip($val, 8) << ($idx << 3);
		}
		return $s_int;
	}

	public function numToString($num) {
		$ret = '';
		for($i = 0; $i < 8; $i++) {
			$byte = $this->flip(($num >> ($i << 3)) & 0xFF, 8);
			$ret .= chr($byte);
		}
		return $ret;
	}

	public function __construct($key, $iv = 'in1tIvKi') {
		if(PHP_INT_SIZE < 8) throw new Exception("PHP must be run in 64 bit mode.");

		if(is_string($key)) {
			$key = array_map(function($chr) { return ord($chr); }, str_split($key));
		}
		if(is_array($key)) {
			if(count($key) != 8) throw new Exception("The length of des key must be 64 bits(8 bytes)!");
			$key_int = 0;
			foreach($key as $idx => $val) {
				$key_int |= $this->flip($val, 8) << ($idx << 3);
			}
			$key = $key_int;
		} else {
			$key = intval($key);
		}

		$this->generateSubKeys($key);

		if(is_string($iv))
			$this->secret_iv = $this->stringToNum($iv);
		else
			$this->secret_iv = intval($iv);
	}

	public function encrypt($text) {
		$n = strlen($text);
		$p = 8-($n % 8);
		$n += $p;
		for($i = 0; $i < $p; $i++)
			$text .= chr($p);   // PKCS5 padding

		$cipher = '';
		$iv = $this->secret_iv;
		for($p = 0; $p < $n; $p += 8) {
			$block_text = $this->stringToNum(substr($text, $p, 8)) ^ $iv; // CBC
			$block_cipher = $this->encryptBlock($block_text);
			$cipher .= $this->numToString($block_cipher);
			$iv = $block_cipher;  // CBC
		}

		return $cipher;
	}

	public function decrypt($cipher) {
		$n = strlen($cipher);
		if($n % 8) throw new Exception("The length of des cipher with PKCS5 padding must be a multiple of 8!");

		$text = '';
		$iv = $this->secret_iv;
		for($p = 0; $p < $n; $p += 8) {
			$block_cipher = $this->stringToNum(substr($cipher, $p, 8));
			$block_text = $this->decryptBlock($block_cipher) ^ $iv; // CBC
			$text .= $this->numToString($block_text);
			$iv = $block_cipher;  // CBC
		}

		// PKCS5 padding
		$paddingChar = $text[$n-1];
		$num = ord($paddingChar);
		for($i = 1; $i <= $num; $i++) {
			if($text[$n - $i] !== $paddingChar) throw new Exception("This is not the valid key or the cipher is not encrypted by this algorithm(DES with PKCS5 padding in CBC mode). Please check your key.");
		}

		return substr($text, 0, $n - $num);
	}
}

// testcases
// $des = new Des("secretki");
// var_dump(bin2hex($des->encrypt("hello world! I'm Garzon. h4Ha.")));
// var_dump($des->decrypt(hex2bin("b91c422995fca5db7cba24f79a28c07b188bd0325552bbfe01214b3108a0f2b7")));

if(isset($_GET['action'])) {
	if(isset($_FILES["desData"]["tmp_name"])) {
		$content = file_get_contents($_FILES["desData"]["tmp_name"]);
		$key = isset($_POST['key']) ? strval($_POST['key']) : 'invalid';
		try {
			$des = new Des($key);
			switch($_GET['action']) {
				case 'encrypt':
					$retText = $des->encrypt($content);
					$name = pathinfo($_FILES['desData']['name'])['filename'] . '_encrypted';
					break;
				case 'decrypt':
					$retText = $des->decrypt($content);
					$name = str_replace('_encrypted', '', pathinfo($_FILES['desData']['name'])['filename']);
					break;
				default:
					throw new Exception("Unsupported action.");
			}
			header("Content-Type: application/force-download");
			header("Content-Disposition: attachment; filename={$name}.txt");
			header("Content-Transfer-Encoding: binary");
			die($retText);
		} catch(Exception $e) {
			die('<meta charset="utf8">Error: ' . $e->getMessage());
		}
	}
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>DES加密解密工具</title>
	<script src="<?= DOMAIN ?>/bower_components/jquery/dist/jquery.min.js"></script>
	<script src="<?= DOMAIN ?>/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
	<script src="<?= DOMAIN ?>/bower_components/angular/angular.min.js"></script>
	<script src="<?= DOMAIN ?>/js/util.js"></script>

	<link rel="stylesheet" href="<?= DOMAIN ?>/bower_components/bootstrap/dist/css/bootstrap.min.css">
	<link rel="stylesheet" href="<?= DOMAIN ?>/css/base.css">
</head>

<body>
	<div class="main-block container block-page" ng-app="des">
		<div class="col-sm-12">
			<form ng-controller="mainController" method="post" target="_blank" action="{{actionUrl}}" enctype="multipart/form-data" class="form-horizontal" name="myForm">
				<h3>{{ isActionSelected ? 'DES ' + action : '在线DES加密解密工具 by Garzon' }}</h3>
				<div ng-show="!isActionSelected">
					<a href="#" ng-click="selectEncrypt()" class="btn btn-primary">加密</a>
					<a href="#" ng-click="selectDecrypt()" class="btn btn-success">解密</a>
				</div>
				<div ng-show="isActionSelected">
					<h5>注：若操作成功将自动开始下载，否则显示错误提示</h5>
					<div class="form-group" >
						<div class="row">
							<label for="resumeData" class="col-md-1-2 control-label">请选择文件：</label>
							<div class="col-md-10 row">
								<div class="col-md-6">
									<span class="btn btn-success btn-lg btn-file fabu-form-button">
										<span>上传文件</span>
										<input type="file" id="desData" name='desData' accept=".txt" required="required">
									</span>
								</div>
							</div>
						</div>
					</div>
					<div class="form-group">
						<div class="row">
							<label for="key" class="col-md-1-2 control-label">密钥：</label>
							<div class="row col-md-10">
								<div class="col-md-3">
									<input type="text" class="form-control" ng-model="key" id="key" name='key' placeholder="Must be 8 bytes" required="required" ng-minlength="8" ng-maxlength="8"/>
								</div>
							</div>
						</div>
					</div>
					<span class="red" ng-show="myForm.desData.$error.required">请选择上传的文件！</span>&nbsp;
					<span class="red" ng-show="!myForm.key.$valid">请输入8字节长的密钥！</span>
					<div class="form-group">
						<div class="col-sm-offset-1 col-sm-6">
							<input type="submit" class="btn btn-lg btn-primary" value="{{action}} !" ng-disabled="!myForm.$valid" />
							&nbsp; &nbsp;
							<a href="#" ng-click="selectBack()" class="btn btn-default">后退</a>
						</div>
					</div>
				</div>
			</form>
		</div>
	</div>
	<script>
		var desApp = angular.module('des', []);
		desApp.controller('mainController', function($scope) {
			$scope.selectEncrypt = function() {
				$scope.isActionSelected = true;
				$scope.action = 'encrypt';
				$scope.actionUrl = '?action=' + $scope.action;
			};
			$scope.selectDecrypt = function() {
				$scope.isActionSelected = true;
				$scope.action = 'decrypt';
				$scope.actionUrl = '?action=' + $scope.action;
			};
			$scope.selectBack = function() {
				$scope.isActionSelected = false;
				$scope.action = '';
			};
			$scope.selectBack();
		});
	</script>
</body>
</html>