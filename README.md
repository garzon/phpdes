# phpdes by garzon

### 实现
这是一个用64位php和位运算实现的des算法，带PKCS5 padding，使用CBC模式，界面使用html+bootstrap+angularjs实现，推荐使用最新Chrome浏览器浏览

### 程序
由于这是一个Web app，并没有可执行程序，请点击下面的链接查看（可能需翻墙）
[http://garzon.science/phpdes/des.php](phpdes)

### 源代码
为了方便，代码都在des.php文件里，请查看des.php即可。    
主要分为几个class：
- DesModel: 分好块的DES核心算法
- Des: 处理字符串等IO格式，以及分块，PKCS5 padding及CBC等
- BitwiseOperation: 处理位运算等

### 使用说明
1. 在菜单界面选择进行“加密”或“解密”操作
2. 在表单界面，选择要加密/解密的txt文件
3. 输入8字节的密钥
4. 点击按钮提交，成功时将会下载解密/加密好的txt文件，失败时会输出错误信息

### 注意事项
解密时，若密钥错误或不是CBC模式或不是PKCS5 padding的原文，将会输出错误信息，不会返回结果。

### 实验

```php
// testcases
$des = new Des("secretki");
var_dump(bin2hex($des->encrypt("hello world! I'm Garzon. h4Ha.")));
// 上面的语句输出b91c422995fca5db7cba24f79a28c07b188bd0325552bbfe01214b3108a0f2b7（即用hex编码的加密后的二进制字符串）

var_dump($des->decrypt(hex2bin("b91c422995fca5db7cba24f79a28c07b188bd0325552bbfe01214b3108a0f2b7")));
// 上面的语句输出hello world! I'm Garzon. h4Ha. 证明解密算法正确
```