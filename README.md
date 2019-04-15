## 非常简单的radius用户管理程序

按照 https://github.com/bg6cq/ITTS/blob/master/other/eduroam/README.md 可以很容易建立eduroam radius服务器，如果对接了无线控制器，让其他单位的用户来本单位使用网络。

这里提供一个简单的radius用户管理程序，可以在不对接自己学校认证系统的情况下，实现简单的radius用户管理。

### 1. 安装
```
cd /usr/src
git clone https://github.com/bg6cq/eduroam-user.git
ln -s /usr/rc/eduroam-user/web /var/www/html/eduroam-user
```

### 2. 数据库设置

在原有freeradius 的radius库中，增加了userinfo、syslog 2个表。

如果使用非默认的radius/radpass登录，请修改db.php中内容。

注意：这里给radius用户增加了radcheck的写权限，默认freeradius只有读权限。

```
CREATE TABLE `userinfo` (
  `username` varchar(50) NOT NULL,
  `truename` varchar(20) DEFAULT NULL,
  `bianhao` varchar(20) DEFAULT NULL,
  `expire` date NOT NULL,
  `memo` varchar(100) DEFAULT NULL,
  `isadmin` int(1) DEFAULT NULL,
   PRIMARY KEY (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `syslog` (
  `tm` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `username` varchar(64) NOT NULL DEFAULT '',
  `ip` varchar(30) NOT NULL DEFAULT '',
  `msg` varchar(253) DEFAULT NULL,
  KEY `syslog_user_tm` (`username`,`tm`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

INSERT INTO `userinfo` VALUES ('admin','管理员','','2030-12-31','',1);
INSERT INTO radcheck (username,attribute,op,value) values ('admin','Cleartext-Password',':=','admin');

GRANT ALL on radius.radcheck TO 'radius'@'localhost';

GRANT ALL on radius.userinfo TO 'radius'@'localhost';
GRANT ALL on radius.syslog TO 'radius'@'localhost';
flush privileges;
```

### 3. 使用

http://x.x.x.x/eduroam-user 

用户admin/密码admin登录即可。

### 4. 其他

db.php中，nthash_pass 设置为0时，数据库中存放明文密码，设置为1时，存放NTHash密码。

运行过程中随时可以修改nthash_pass，调整后原存放的密码不会有任何变化，新修改/新添加用户的密码才会按照设置用明文/NTHash方式存放。
