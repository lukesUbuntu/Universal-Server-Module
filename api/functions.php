<?php
//  CyanDark Incorporated
//  Copyright (c) 2012-2015 CyanDark, Inc. All Rights Reserved.
// 
//  This software is furnished under a license and may be used and copied
//  only  in  accordance  with  the  terms  of such  license and with the
//  inclusion of the above copyright notice.  This software  or any other
//  copies thereof may not be provided or otherwise made available to any
//  other person.  No title to and  ownership of the  software is  hereby
//  transferred.
// 
//  You may not reverse  engineer, decompile, defeat  license  encryption
//  mechanisms, or  disassemble this software product or software product
//  license. CyanDark may terminate this license if you don't comply with
//  any of the  terms  and conditions  set  forth in our end user license
//  agreement (EULA).  In such event, licensee  agrees to return licensor
//  or  destroy all copies  of  software  upon termination of the license.

	function ping($host,$port=22,$timeout=6){
		set_time_limit(10);
        $fsock = fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$fsock){ return FALSE; } else { return TRUE; }
	}

	function serverIsUp($server){
		set_time_limit(3);
		exec ("/bin/ping -c 1 -i 0.2 -W 1 ".$server, $r); 
		$r = print_r($r, true);
		if(preg_match("/bytes from/i", $r)){ return true; } else { return false; }
	}

	function ItsSshActivated($server, $port, $user, $pass){
		if(ping($server, $port)){
			$connection = ssh2_connect($server, $port);
			if(ssh2_auth_password($connection, $user, $pass)){ return true; } else { return false; }
    	}
    }

	function getFreeMemory($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'free -m');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream);    
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace("-/+","", $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return $data[10];
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }

	function getUsedMemory($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'free -m');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream);    
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace("-/+","", $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return $data[9];
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
    
	function getTotalMemory($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'free -m');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream);    
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace("-/+","", $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return $data[8];
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
    

	function getUsedDisk($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'df -m');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		$data = array_filter(explode("\n", $data));
    		unset($data[0]);
    		foreach($data as $a){
    			if(preg_match("/% \//i", $a) && !preg_match("/sd/i", $a) && !preg_match("/dev/i", $a) && !preg_match("/boot/i", $a) && !preg_match("/swap/i", $a)){
    				$data = $a;
    			}
    		}
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return $data[2];
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }

	function getFreeDisk($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'df -m');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		$data = array_filter(explode("\n", $data));
    		unset($data[0]);
    		foreach($data as $a){
    			if(preg_match("/% \//i", $a) && !preg_match("/sd/i", $a) && !preg_match("/dev/i", $a) && !preg_match("/boot/i", $a) && !preg_match("/swap/i", $a)){
    				$data = $a;
    			}
    		}
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return $data[3];
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
    
	function getTotalDisk($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'df -m');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		$data = array_filter(explode("\n", $data));
    		unset($data[0]);
    		foreach($data as $a){
    			if(preg_match("/% \//i", $a) && !preg_match("/sd/i", $a) && !preg_match("/dev/i", $a) && !preg_match("/boot/i", $a) && !preg_match("/swap/i", $a)){
    				$data = $a;
    			}
    		}
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return $data[1];
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }

	function getCpuUsage($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'top -bn 1| grep Cpu');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return floatval($data[1]);
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
    
	function getCpuIdle($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'top -bn 1| grep Cpu');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		echo $data;
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return floatval($data[4]);
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }

	function rebootServerSSH($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'reboot');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		echo $data;
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return null;
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }

	function offServerSSH($server, $port, $user, $pass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'halt');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		$data = preg_replace('!\s+!', ' ', $data);
    		$data = str_replace(" ","-", $data);
    		$data = array_filter(explode("-", $data));
    		return null;
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
    
	function changeServerHostnameSSH($server, $port, $user, $pass, $hostname){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'hostname '.$hostname.'; hostname');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		return $data;
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }

	function changeServerPasswordSSH($server, $port, $user, $pass, $newpass){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			$stream = ssh2_exec($connection, 'echo -e "'.$newpass.'\n'.$newpass.'" | passwd root');
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		return $data;
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
    
	function upgradeSoftwareSSH($server, $port, $user, $pass, $os){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			if($os == 'rhel'){ $command = 'killall yum; yum -y update'; } else { if($os == 'bsd'){ $command = 'sudo pkg update -y'; } else { $command = 'apt-get -y update'; } }
			$stream = ssh2_exec($connection, $command);
    		return $stream;
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
    
	function cleanSoftwareSSH($server, $port, $user, $pass, $os){
		$connection = ssh2_connect($server, $port);
		if(ssh2_auth_password($connection, $user, $pass)){
			if($os == 'rhel'){ $command = 'yum -y clean'; } else { if($os == 'bsd'){ $command = 'sudo pkg clean'; } else { $command = 'apt-get clean'; } }
			$stream = ssh2_exec($connection, $command);
			stream_set_blocking($stream, true);
			$data = '';
        		while($buffer = fread($stream, 4096)) {
            		$data = $buffer;
        		}
    		fclose($stream); 
    		return $data;
    	} else {
    		die('Error connecting to '.$server.' via SSH.');
    	}
    }
?>