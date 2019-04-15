<?php
//
// by james@ustc.edu.cn 2019.04
//

include("db.php");

date_default_timezone_set('Asia/Chongqing');

function NTHash($Input) {
  $Input = iconv('UTF-8', 'UTF-16LE', $Input);
  return(strtoupper(hash('md4',$Input)));
}

function checkvalue($str) {
	for ($i = 0; $i < strlen($str); $i ++) {
        	if (ctype_alnum($str[$i]))  continue;
		if (strchr("@-_ ./:", $str[$i])) continue;
        	echo "$str中第 $i 非法字符 $str[$i]";
		exit(0);
	}
}

function safe_get($str) {
	@$x = $_REQUEST[$str];
	checkvalue($x);
	return $x;
}

function op_display($op) {
	global $mysqli;
	if ($op == "") 
		echo "";
	else {
		$q = "select truename from userinfo where username=?";
		$stmt = $mysqli->prepare($q);
        	$stmt->bind_param("s",$op);
        	$stmt->execute();
        	$stmt->bind_result($r[0]);
		if($stmt->fetch()) {
			echo $r[0];
		} else 
			echo $op.":未知管理员";
		$stmt->close();
        }
}

function get_expdate($expire) {
	if(strstr($expire, "-")) {
		$expdate = date_create($expire);
	} else {
		$expdate = date_create();
		date_add($expdate, date_interval_create_from_date_string("$expire days"));
	}
	return $expdate;
}

function userisadmin() {
	global $mysqli;
	$username = $_SESSION["username"];
	$q = "select isadmin from userinfo where username = ?";
	$stmt = $mysqli->prepare($q);
       	$stmt->bind_param("s", $username);
       	$stmt->execute();
       	$stmt->bind_result($r[0]);
	$stmt->fetch();  
	$stmt->close();
	if ($r[0] == "1") // operator 
		return 1;  // full right
	return 0;
}

$cmd = safe_get("cmd");

?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" /> 
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=8.0"/>
<link href="table.css" type="text/css" rel="stylesheet" /> 
<title>eduroam用户管理</title>
</head>
<body>

<?php

if ($cmd=="logout") {
	$_SESSION["login"] = 0;
	$_SESSION["username"] = 0;
	$_SESSION["truename"] = "";
	$_SESSION["isadmin"] = 0;
	$_SESSION["expire"] = "";
	echo "<p>已经退出登录";
}

function do_login() {
	global $mysqli;
	$username = safe_get("user");
	$pass = $_REQUEST["pass"];

	if ($username == ""){
		echo "<font color=red>请输入用户名</font>";
		return 0;
	}
	$q = "select attribute, value from radcheck where username = ? and (attribute='NT-Password' or attribute='Cleartext-Password')";
	$stmt = $mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();
       	$stmt->bind_result($r[0],$r[1]);
	if(!$stmt->fetch()) {
		sleep(2);
		echo "<font color=red>用户不存在或密码错</font>";
		return 0;
	}

	$passok = 0;
	if(($r[0] == 'NT-Password') && (NTHash($pass) == $r[1]))
		$passok = 1;
	if(($r[0] == 'Cleartext-Password') && ($pass == $r[1])) 
		$passok = 1;
	if($passok == 0) {
		sleep(2);
		echo "<font color=red>用户不存在或密码错</font>";
		return 0;
	}
	$stmt->close();

	$q = "select now()<=expire, truename, expire from userinfo where username=?";
	$stmt = $mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();
       	$stmt->bind_result($r[0],$r[1],$r[2]);
	if(!$stmt->fetch()) {
		echo "<font color=red>用户信息不完整，请联系管理员</font>";
		return 0;
	}
	if($r[0] != "1") {
		echo "<font color=red>用户信息不在有效期内，请联系管理员</font>";
		return 0;
	}
	$stmt->close();
	$_SESSION["login"] = 1;
	$_SESSION["username"] = $username;
	$_SESSION["truename"] = $r[1];
	$_SESSION["expire"] = $r[2];
	$_SESSION["isadmin"] = userisadmin();
	
	$q = "insert into syslog values(now(),?,?,?)";
	$stmt = $mysqli->prepare($q);
	$ip = $_SERVER['REMOTE_ADDR'];
	$msg="登录";
       	$stmt->bind_param("sss",$username,$ip,$msg);
       	$stmt->execute();
	return 1;
}

if ($cmd == "login") {
	if(do_login()) {
		@$isadmin = $_SESSION["isadmin"];
		if($isadmin == 1)
			$cmd = "listuser";
		else  {
			$cmd = "list";
	//		$_SESSION["login"] = 0;
	//		echo "<font color=red>普通用户不允许登录</font>";
	//		exit(0);
		}
	}
} // end cmd==login


@$login = $_SESSION["login"];
@$isadmin = $_SESSION["isadmin"];
if ($login <> 1) {   // 用户没有登录
	$login = 0;
	$_SESSION["login"] = 0;
	echo "<p>";
	echo "请输入用户名和密码登录<p>";
	echo "<form action=index.php method=post>";
	echo "<input name=cmd type=hidden value=login>";
	echo "用户:<input name=user><br>";
	echo "密码:<input name=pass type=password><p>";
	echo "<input type=submit value=\"登 录\"></form>\n";
	exit(0);
} // login <> 1

echo "<ul class=\"nav\">\n";

if($isadmin == 0) {
	echo "<li><dl>";
	echo "<dt><a href=index.php?cmd=list>用户查询</a></dt>";
	echo "</dl></li>\n";
}

if($isadmin == 1) {
	echo "<li><dl>";
	echo "<dt><a href=index.php?cmd=listuser>用户列表</a></dt>";
	echo "</dl></li>\n";

	echo "<li><dl>";
	echo "<dt><a href=index.php?cmd=newuser>开户</a></dd>";
	echo "</dl></li>\n";

	echo "<li><dl>";
	echo "<dt><a href=index.php?cmd=stat>在线用户</a></dd>";
	echo "</dl></li>\n";
	
	echo "<li><dl>";
	echo "<dt><a href=index.php?cmd=uselog>上网日志</a></dd>";
	echo "</dl></li>\n";

	echo "<li><dl>";
	echo "<dt><a href=index.php?cmd=syslog>操作日志</a></dd>";
	echo "</dl></li>\n";
}

echo "<li><dl>";
echo "<dt><a href=index.php?cmd=changepass>修改密码</a></dt>";
echo "</dl></li>\n";

echo "<li><dl>";
echo "<dt><a href=index.php?cmd=logout>退出</a></dt>";
echo "</dl></li>\n";

echo "</ul>\n";
echo "<div id=\"navbg\"></div><p>\n";

if ($cmd == "" ) 
	$cmd = "list";

function list_one_user($username) {
	global $mysqli;
	echo "<h3>用户".$username."信息</h3>\n";
	$q = "select truename, expire, bianhao from userinfo where username = ?";
	$stmt = $mysqli->prepare($q);
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$stmt->bind_result($r[0], $r[1], $r[2]);
	$stmt->fetch();
	$stmt->close();
	echo "<table border=1 cellspacing=0>";
	echo "<tr><th>姓名</th><th>有效期</th><th>编号</th></tr>\n";
	echo "<tr><td align=right>";
	echo $r[0];
	echo "</td><td>";
	echo $r[1];
	echo "</td><td>";
	echo $r[2];
	echo "</td></tr>";
	echo "</table>";

	echo "<h3>用户".$username."当前在线记录</h3>\n";
	$q= "select acctstarttime, acctstoptime, acctinputoctets, acctoutputoctets, framedipaddress, calledstationid, callingstationid from radacct where username = ? and acctstoptime is null order by acctstarttime";
	$stmt = $mysqli->prepare($q);
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$stmt->bind_result($r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]);
	echo "<table border=1 cellspacing=0>";
	echo "<tr><th>开始时间</th><th>入流量</th><th>出流量</th><th>IP</th><th>服务</th><th>用户MAC</th></tr>\n";
	while($stmt->fetch()) {
		echo "<tr><td>";
		echo $r[0];
		echo "</td><td align=right>";
		echo $r[2];
		echo "</td><td align=right>";
		echo $r[3];
		echo "</td><td>";
		echo $r[4];
		echo "</td><td>";
		echo $r[5];
		echo "</td><td>";
		echo $r[6];
		echo "</td></tr>";
	}
	echo "</table>";
	$stmt->close();

	echo "<h3>用户".$username."最近日志</h3>\n";
	$q = "select tm, ip, msg from syslog where username = ? order by tm desc limit 20";
	$stmt = $mysqli->prepare($q);
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$stmt->bind_result($r[0], $r[1], $r[2]);
	echo "<table border=1 cellspacing=0>";
	echo "<tr><th>时间</th><th>IP</th><th>日志</th></tr>\n";
	while($stmt->fetch()) {
		echo "<tr><td>";
		echo $r[0];
		echo "</td><td>";
		echo $r[1];
		echo "</td><td>";
		echo $r[2];
		echo "</td></tr>";
	}
	$stmt->close();
	echo "</table>";

	echo "<h3>用户".$username."最近使用记录</h3>\n";
	$q="select acctstarttime, acctstoptime, acctinputoctets, acctoutputoctets, framedipaddress, calledstationid, callingstationid from radacct where username = ? order by acctstarttime desc limit 10";
	$stmt = $mysqli->prepare($q);
	$stmt->bind_param("s", $username);
	$stmt->execute();
	$stmt->bind_result($r[0], $r[1], $r[2], $r[3], $r[4], $r[5], $r[6]);
	echo "<table border=1 cellspacing=0>";
	echo "<tr><th>开始时间</th><th>结束时间</th><th>入流量</th><th>出流量</th><th>IP</th><th>服务</th><th>用户MAC</th></tr>\n";
	while($stmt->fetch()) {
		echo "<tr><td>";
		echo $r[0];
		echo "</td><td>";
		echo $r[1];
		echo "</td><td align=right>";
		echo $r[2];
		echo "</td><td align=right>";
		echo $r[3];
		echo "</td><td>";
		echo $r[4];
		echo "</td><td>";
		echo $r[5];
		echo "</td><td>";
		echo $r[6];
		echo "</td></tr>";
	}
	echo "</table>";
	$stmt->close();
}

// list   用户查询
if ($cmd == "list") {
	$username = $_SESSION["username"];
	list_one_user($username);
	exit(0);
}

if ($cmd == "changepass_do") {
	global $mysqli;
	$username = $_SESSION["username"];
	@$oldpass = $_REQUEST["oldpass"];
	@$pass1 = $_REQUEST["pass1"];
	@$pass2 = $_REQUEST["pass2"];

	if($username == "test") {
		echo "<font color=red>用户test禁止修改密码</font><p>\n";
		exit(0);
	}
	if($pass1 != $pass2) {
		echo "<font color=red>两次输入的新密码不相同</font><p>\n";
	} else {
		$q = "select id, attribute, value from radcheck where username = ? and (attribute='NT-Password' or attribute='Cleartext-Password')";
		$stmt = $mysqli->prepare($q);
       		$stmt->bind_param("s", $username);
       		$stmt->execute();
       		$stmt->bind_result($id, $r[0], $r[1]);
		if(!$stmt->fetch()) {
			echo "<font color=red>用户不存在</font>";
			exit; 
		}
		$stmt->close();
		if((($r[0] == 'NT-Password') && (NTHash($oldpass) != $r[1])) || (($r[0] == 'Cleartext-Password') && ($oldpass != $r[1]))) { //pass error
			echo "<font color=red>旧密码错误，请检查</font>";
			exit; 
		}

		if($nthash_pass == 1) {
			$pass1 = NTHash($pass1);
			$q = "update radcheck set value=?, attribute='NT-Password' where id=?";
		} else
			$q = "update radcheck set value=?, attribute='Cleartext-Password' where id=?";
		$stmt = $mysqli->prepare($q);
       		$stmt->bind_param("ss",$pass1,$id);
       		$stmt->execute();
		$q = "insert into syslog values(now(),?,?,?)";
		$stmt = $mysqli->prepare($q);
		$ip = $_SERVER['REMOTE_ADDR'];
		$msg = "修改密码";
       		$stmt->bind_param("sss", $username, $ip, $msg);
       		$stmt->execute();
		echo "密码修改完成<p>\n";
		exit;
	}
	$cmd = "changepass";
}

if ($cmd == "changepass") {
?>

<h3>修改密码</h3>
<form action=index.php method=post>
<input type=hidden name=cmd value=changepass_do>
旧密码：<input type=password name=oldpass><p>
新密码：<input type=password name=pass1><p>
新密码：<input type=password name=pass2><p>
<input type=submit value="修改密码">
</form>

<?php
	exit(0);
}	

if ($isadmin == 0) {
	echo "抱歉，您没有相关权限\n";
	exit(0);
}

if ($cmd == "del_one_user") {
	$username = safe_get("username");
	if($username == $_SESSION["username"]) {
		echo "<font colore=red>您不能删除自己</font>";
		exit(0);
	}
	$q = "delete from radcheck where username=?";
	$stmt = $mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();
	$q = "delete from userinfo where username=?";
	$stmt = $mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt = $mysqli->prepare($q);
	$ip = $_SERVER['REMOTE_ADDR'];
	$msg = "管理员".$_SESSION["username"]."删除帐号";
       	$stmt->bind_param("sss",$username,$ip,$msg);
       	$stmt->execute();

	echo "用户".$username."已经删除";
	exit(0);
}

if ($cmd == "set_user_pass") {
	$username = safe_get("username");
	$pass = $_REQUEST["pass"];
	if($nthash_pass == 1) {
		$pass = NTHash($pass);
		$q = "update radcheck set attribute='NT-Password', value=? where username=? and (attribute='NT-Password' or attribute='Cleartext-Password')";
	} else
		$q = "update radcheck set attribute='Cleartext-Password', value=? where username=? and (attribute='NT-Password' or attribute='Cleartext-Password')";
	$stmt = $mysqli->prepare($q);
       	$stmt->bind_param("ss", $pass, $username);
       	$stmt->execute();
	$q = "insert into syslog values(now(),?,?,?)";
	$stmt = $mysqli->prepare($q);
	$ip = $_SERVER['REMOTE_ADDR'];
	$msg = "管理员".$_SESSION["username"]."重设密码";
       	$stmt->bind_param("sss", $username, $ip, $msg);
       	$stmt->execute();
	echo "密码修改完成<p>\n";
	echo "<a href=index.php?cmd=list_one_user&username=".$username.">点击这里查看用户信息</a>";
	exit;
}

if ($cmd == "set_user_offline") {
	$username = safe_get("username");
	$q = "update radacct set acctstoptime=now(),acctterminatecause='sysadmin set'  where username=? and acctstoptime is null";
	$stmt = $mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt = $mysqli->prepare($q);
	$ip = $_SERVER['REMOTE_ADDR'];
	$msg = "管理员".$_SESSION["username"]."强制用户下线";
       	$stmt->bind_param("sss",$username,$ip,$msg);
       	$stmt->execute();
	echo "强制下线完成<p>\n";
	echo "<a href=index.php?cmd=list_one_user&username=".$username.">点击这里查看用户信息</a>";
	exit;
}

if ($cmd=="modi_user_do"){
	$username = safe_get("username");
	$q = "select value from radcheck where username=? and (attribute='NT-Password' or attribute='Cleartext-Password')";
	$stmt=$mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();
       	$stmt->bind_result($r[0]);
	if(!$stmt->fetch()) {
		echo "用户".$username."不存在";
		exit(0);
	}
	$stmt->close();
	$expire = $_REQUEST["expire"];
	$expdate = get_expdate($expire);

	$truename=$_REQUEST["truename"];	
	$bianhao=$_REQUEST["bianhao"];	
	$memo=$_REQUEST["memo"];	

	$q = "update userinfo set truename=?,bianhao=?,expire=?,memo=? where username=?";
	$stmt=$mysqli->prepare($q);
	$expstr=date_format($expdate,"Y-m-d");
       	$stmt->bind_param("sssss",$truename,$bianhao,$expstr,$memo,$username);
       	$stmt->execute();

	$q = "update radcheck set value=? where username=? and attribute='Expiration'";
	$stmt=$mysqli->prepare($q);
	$expstr=date_format($expdate,"M d Y 23:59:59");
       	$stmt->bind_param("ss",$expstr,$username);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt=$mysqli->prepare($q);
	$ip=$_SERVER['REMOTE_ADDR'];
	$msg="管理员".$_SESSION["username"]."修改用户信息";
       	$stmt->bind_param("sss",$username,$ip,$msg);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt=$mysqli->prepare($q);
	$ip=$_SERVER['REMOTE_ADDR'];
	$op=$_SESSION["username"];
	$expstr=date_format($expdate,"Y-m-d");
	$msg="管理员".$op."修改用户:".$username."/".$truename."/".$bianhao."/".$expstr."/".$memo;
       	$stmt->bind_param("sss",$op,$ip,$msg);
       	$stmt->execute();
	echo "修改操作完成<p>\n";
	echo "<a href=index.php?cmd=list_one_user&username=".$username.">点击这里查看用户信息</a>";
	exit;
}

if ($cmd=="list_one_user"){
	$username=safe_get("username");
	list_one_user($username);

	echo "<h3>管理员操作</h3>";

	echo "<hr width=300 align=left>";

	echo "<hr width=300 align=left>";
	echo "<form action=index.php method=post>";
	echo "<input type=hidden name=username value=".$username.">";
	echo "<input type=hidden name=cmd value=set_user_pass>";
	echo "设置用户新密码<br><input name=pass>";
	echo "<input type=submit value=\"设置用户密码\">";
	echo "</form>\n";
	echo "<hr width=300 align=left>";

	echo "修改用户信息<p>";
	$q = "select truename,bianhao,memo,expire from userinfo where username=?";
	$stmt=$mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();
       	$stmt->bind_result($truename,$bianhao,$memo,$expire);
	$stmt->fetch();
	$stmt->close();

	echo "<form action=index.php method=post>";
	echo "<input type=hidden name=cmd value=modi_user_do>";
	echo "用户: <input name=username value=\"$username\">登录名<p>";
	echo "时效: <input name=expire value=\"".$expire."\">2016-10-1或者从今天开始的天数, -1禁止用户，0表示今天可用<p>";
	echo "姓名：<input name=truename value=\"".$truename."\"><p>";
	echo "编号：<input name=bianhao value=\"".$bianhao."\">用户可以看到编号<p>";
	echo "备注：<input name=memo value=\"".$memo."\">用户看不到备注<p>";
	echo "<input type=submit value=\"修改用户信息\">";
	echo "</form>";

	echo "<h3>删除用户</h3>";
	echo "<hr width=300 align=left>";
	echo "<form action=index.php method=post>";
	echo "<input type=hidden name=username value=".$username.">";
	echo "<input type=hidden name=cmd value=del_one_user>";
	echo "<input type=submit value=\"删除用户\" onclick=\"return confirm('删除用户 $username ?');\"><font color=red>谨慎操作</font>";
	echo "</form>\n";
	exit(0);
}

if ($cmd=="listuser") {
	global $mysqli;
	$sort = safe_get("s");
	
	$q = "select userinfo.username,userinfo.truename,userinfo.bianhao,userinfo.expire,userinfo.memo from userinfo order by username";

	if ($sort=="n")
		$q = "select userinfo.username,userinfo.truename,userinfo.bianhao,userinfo.expire,userinfo.memo from userinfo order by truename";
	else if ($sort=="b")
		$q = "select userinfo.username,userinfo.truename,userinfo.bianhao,userinfo.expire,userinfo.memo from userinfo order by bianhao";
	else if ($sort=="e")
		$q = "select userinfo.username,userinfo.truename,userinfo.bianhao,userinfo.expire,userinfo.memo from userinfo order by expire";
	$rr = $mysqli->query($q);

	echo "<table border=1 cellspacing=0>";
	echo "<tr><th>序号</th><th><a href=index.php?cmd=listuser>用户</a></th>";
	echo "<th><a href=index.php?cmd=listuser&s=n>姓名</a></th><th><a href=index.php?cmd=listuser&s=b>编号</a></th><th><a href=index.php?cmd=listuser&s=e>失效期</a></th><th>备注</th>";
	echo "</tr>";
	$count = 0;
	while ($r=$rr->fetch_array()){
		$count++;
		echo "<tr>";
		echo "<td align=center><a href=index.php?cmd=list_one_user&username=$r[0]>$count</a></td>";
		echo "<td><a href=index.php?cmd=list_one_user&username=$r[0]>$r[0]</a></td>";
		echo "<td>";
		echo $r[1];
		echo "</td>";
		echo "<td>";
		echo $r[2];
		echo "</td>";
		echo "<td>";
		echo $r[3];
		echo "</td>";
		echo "<td>";
		echo $r[4];
		echo "</td>";
		echo "</tr>";
		echo "\n";
	}
	echo "</table>\n";
	exit(0);
} // 

if ($cmd=="new_user_do"){
	$username = safe_get("username");
	$q = "select value from radcheck where username=? and (attribute='NT-Password' or attribute='Cleartext-Password')";
	$stmt=$mysqli->prepare($q);
       	$stmt->bind_param("s",$username);
       	$stmt->execute();
       	$stmt->bind_result($r[0]);
	if($stmt->fetch()) {
		echo "用户".$username."已经存在，开户失败";
		exit(0);
	}
	$pass = $_REQUEST["pass"];
	$expire = $_REQUEST["expire"];
	$expdate = get_expdate($expire);
	
	if($nthash_pass==1) {
		$pass = NTHash($pass);
		$q = "insert into radcheck (username,attribute,op,value) values(?,'NT-Password',':=',?)";
	} else
		$q = "insert into radcheck (username,attribute,op,value) values(?,'Cleartext-Password',':=',?)";
	$stmt=$mysqli->prepare($q);
       	$stmt->bind_param("ss",$username,$pass);
       	$stmt->execute();

	$truename=$_REQUEST["truename"];	
	$bianhao=$_REQUEST["bianhao"];	
	$memo=$_REQUEST["memo"];	

	$q = "insert into userinfo values(?,?,?,?,?,0)";
	$stmt=$mysqli->prepare($q);
	$expstr=date_format($expdate,"Y-m-d");
       	$stmt->bind_param("sssss",$username,$truename,$bianhao,$expstr,$memo);
       	$stmt->execute();

	$q = "insert into radcheck(username,attribute,op,value) values(?,'Expiration','==',?)";
	$stmt=$mysqli->prepare($q);
	$expstr=date_format($expdate,"M d Y 23:59:59");
       	$stmt->bind_param("ss",$username,$expstr);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt=$mysqli->prepare($q);
	$ip=$_SERVER['REMOTE_ADDR'];
	$msg="管理员".$_SESSION["username"]."新增用户";
       	$stmt->bind_param("sss",$username,$ip,$msg);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt=$mysqli->prepare($q);
	$ip=$_SERVER['REMOTE_ADDR'];
	$op=$_SESSION["username"];
	$expstr=date_format($expdate,"Y-m-d");
	$msg="管理员".$op."新增用户:".$username."/".$truename."/".$bianhao."/".$expstr."/".$memo;
       	$stmt->bind_param("sss",$op,$ip,$msg);
       	$stmt->execute();
	echo "开户操作完成<p>\n";
	echo "<a href=index.php?cmd=list_one_user&username=".$username.">点击这里查看用户信息</a>";
	exit(0);
}

if ($cmd=="newuser") {
?>
<h3>新用户开户</h3>
<form action=index.php method=post>
<input type=hidden name=cmd value=new_user_do>
用户: <input name=username>登录名<p>
密码: <input name=pass><p>
时效: <input name=expire value='2030-12-1'>2016-10-1或者从今天开始的天数, -1禁止用户，0表示今天可用<p>
姓名：<input name=truename><p>
编号：<input name=bianhao>用户可以看到编号<p>
备注：<input name=memo>用户看不到备注<p>
<input type=submit value="增加用户">

<?php
}

if ($cmd=="set_alluser_offline") {
	$q = "update radacct set acctstoptime=now(),acctterminatecause='sysadmin set' where acctstoptime is null";
	$stmt=$mysqli->prepare($q);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt=$mysqli->prepare($q);
	$ip=$_SERVER['REMOTE_ADDR'];
	$msg="管理员".$_SESSION["username"]."强制所有用户下线";
	$username="all";
       	$stmt->bind_param("sss",$username,$ip,$msg);
       	$stmt->execute();
	echo "强制下线完成<p>\n";
	exit;
}

if ($cmd=="set_oneuser_offline") {
	$radacctid=safe_get("radacctid");
	$username=safe_get("username");
	$q = "update radacct set acctstoptime=now(),acctterminatecause='sysadmin set'  where radacctid=? and acctstoptime is null";
	$stmt=$mysqli->prepare($q);
       	$stmt->bind_param("s",$radacctid);
       	$stmt->execute();

	$q = "insert into syslog values(now(),?,?,?)";
	$stmt=$mysqli->prepare($q);
	$ip=$_SERVER['REMOTE_ADDR'];
	$msg="管理员".$_SESSION["username"]."强制用户下线";
       	$stmt->bind_param("sss",$username,$ip,$msg);
       	$stmt->execute();
	echo "强制下线完成<p>\n";
	exit;
}

if($cmd=="stat") {
	echo "<h3>当前在线用户</h3>\n";
	$q="select acctstarttime,radacct.username,framedipaddress,calledstationid,callingstationid,radacctid from radacct where acctstoptime is null order by acctstarttime";
	$stmt=$mysqli->prepare($q);
	$stmt->execute();
	$stmt->bind_result($acctstarttime,$username,$ip,$service,$mac,$radacctid);
	$stmt->store_result();
	echo "<table border=1 cellspacing=0>";
	$count=0;
	echo "<tr><th>序号</th><th>开始时间</th><th>用户</a><th>IP</th><th>服务</th><th>用户MAC</th><th>操作</th></tr>\n";
	while($stmt->fetch()) {
		$count++;
		echo "<tr><td align=center>";
		echo $count;
		echo "</td><td>";
		echo $acctstarttime;
		echo "</td><td>";
		echo "<a href=index.php?cmd=list_one_user&username=".$username.">";

		$q="select truename from userinfo where userinfo.username=?";
		$stmt2=$mysqli->prepare($q);
		$stmt2->bind_param("s",$username);
		$stmt2->execute();
		$stmt2->bind_result($truename);
		$stmt2->fetch();
		$stmt2->close();
		echo $username."/".$truename;
		echo "</a>";
		
		echo "</td><td>";
		echo $ip;
		echo "</td><td>";
		if($service=="pppoe") 
			echo "PPPoE";
		else if($service=="lanhotspot") 
			echo "有线网页认证";
		else if($service=="wifihotspot") 
			echo "无线网页认证";
		else if(strstr($service,"beijing.1X")) 
			echo "802.1X无线认证";
		else echo $service;
		echo "</td><td>";
		echo $mac;
		echo "</td><td>&nbsp;";
		echo "<a href=index.php?cmd=set_oneuser_offline&radacctid=".$radacctid."&username=".$username." onclick=\"return confirm('确认强制用户下线?');\">强制下线</a>";
		echo "&nbsp;</td></tr>";
	}
	echo "</table>";
	$stmt->close();

	echo "<h3>管理员操作</h3>";

	echo "<hr width=300 align=left>";
	echo "<form action=index.php method=post>";
	echo "<input type=hidden name=cmd value=set_alluser_offline>";
	echo "<input type=submit value=\"强制所有用户下线\" onclick=\"return confirm('确认强制所有用户下线?');\"><br><font color=red>用户设备异常断线后清理之前的上线统计</font>";
	echo "<br>并不会真正将用户断开";
	echo "</form>\n";
	exit(0);
}


if($cmd=="uselog"){
	echo "<h3>用户上线记录</h3>\n";
	$q="select username,acctstarttime,acctstoptime,acctinputoctets,acctoutputoctets,framedipaddress,calledstationid,callingstationid from radacct order by acctstarttime desc limit 200";
	$stmt=$mysqli->prepare($q);
	$stmt->execute();
	$stmt->bind_result($r[0],$r[1],$r[2],$r[3],$r[4],$r[5],$r[6],$r[7]);
	echo "<table border=1 cellspacing=0>";
	echo "<tr><th>开始时间</th><th>结束时间</th><th>用户</th><th>入流量</th><th>出流量</th><th>IP</th><th>服务</th><th>用户MAC</th></tr>\n";
	while($stmt->fetch()) {
		echo "<tr><td>";
		echo $r[1];
		echo "</td><td>";
		echo $r[2];
		echo "</td><td align=right>";
		echo $r[0];
		echo "</td><td align=right>";
		echo $r[3];
		echo "</td><td align=right>";
		echo $r[4];
		echo "</td><td>";
		echo $r[5];
		echo "</td><td>";
		if($r[6]=="pppoe") 
			echo "PPPoE";
		else if($r[6]=="lanhotspot") 
			echo "有线网页认证";
		else if($r[6]=="wifihotspot") 
			echo "无线网页认证";
		else if(strstr($r[6],"beijing.1X")) 
			echo "802.1X无线认证";
		else echo $r[6];
		echo "</td><td>";
		echo $r[7];
		echo "</td></tr>";
	}
	echo "</table>";
	$stmt->close();
	echo "<p>";
	echo "注：流量为当日下线时记录的流量";
	exit(0);
}

if($cmd=="syslog") {
	echo "<h3>最近日志</h3>\n";
	$q = "select tm,username,ip,msg from syslog order by tm desc limit 500";
	$stmt=$mysqli->prepare($q);
	$stmt->execute();
	$stmt->bind_result($r[0],$r[1],$r[2],$r[3]);
	echo "<table border=1 cellspacing=0>";
	echo "<tr><th>时间</th><th>用户</th><th>IP</th><th>日志</th></tr>\n";
	while($stmt->fetch()) {
		echo "<tr><td>";
		echo $r[0];
		echo "</td><td>";
		echo $r[1];
		echo "</td><td>";
		echo $r[2];
		echo "</td><td>";
		echo $r[3];
		echo "</td></tr>";
	}
	$stmt->close();
	echo "</table>";
}
?>
