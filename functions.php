<?php

	function score ($list,$data,&$matches = null) {
		$score = 0;
		foreach ($list as $preg => $pts) {
			//echo "Parsing $preg...\n";			
			if ($x = preg_match_all($preg.'S',$data,$m)) {
				$matches[$preg] = $x;
				$score += $pts * $x;
			}
		}
		return $score;
	}
	function getWarningLevel( $user ) {
		global $wpq;
		$warning = 0;
		$talk = $wpq->getpage('User talk:'.$user);
		if (preg_match_all('/<!-- Template:uw-[a-z]*(\d)(im)? -->.*(\d{2}):(\d{2}), (\d+) ([a-zA-Z]+) (\d{4}) \(UTC\)/iU', $talk,$m,PREG_SET_ORDER)) {
			foreach ($m as $r) {
				$month = array('January' => 1, 'February' => 2, 'March' => 3,'April' => 4, 'May' => 5, 'June' => 6, 'July' => 7, 'August' => 8, 'September' => 9, 'October' => 10,'November' => 11, 'December' => 12);
				if ((time() - gmmktime($r[3],$r[4],0,$month[$r[6]],$r[5],$r[7])) <= (2*24*60*60)) {
					if ($r[1] > $warning) { $warning = $r[1]; }
				}
			}
		}
		return $warning;
	}
	function in_arrayi( $needle, $haystack ) {
		$found = false;
		foreach( $haystack as $value ) {
			if( strtolower( $value ) == strtolower( $needle ) ) {
				$found = true;
			}
		}	
		return $found;
	}
 
	function sig_handler($signo) {
		global $pids, $irc, $feed, $nick;
		switch ($signo) {
			case SIGCHLD:
				while (($x = pcntl_waitpid(0, $status, WNOHANG)) != -1) {
					if ($x == 0) break;
					$status = pcntl_wexitstatus($status);
				}
				break;
			case SIGTERM:
				print_r($pids);
				foreach($pids as $pid) {
 
					echo "Killing $pid\n";
					posix_kill($pid, SIGINT);
				}
				echo "Killing ".posix_getpid()."\n";
				fwrite( $irc,'QUIT '.$user." :Killed by X!\n" );
				fwrite( $feed,'QUIT '.$user." :Killed by X!\n" );
				fclose($irc);
				fclose($feed);
				die();
            case SIGINT:
            	exit();
            	break;
		}
	}
	
	function secs2str($secs) {
        $units = array(
                "w" => 7 * 24 * 60 * 60,
                "d" =>     24 * 60 * 60,
                "h" =>          60 * 60,
                "m" =>               60,
                "s" =>                1,
        );
		
		if ( $secs == 0 ) return "0 s";

        $s = null;
        foreach ( $units as $name => $divisor ) {
        	if ( $quot = intval($secs / $divisor) ) {
            	$s .= "$quot $name";
            	$s .= ", ";
            	$secs -= $quot * $divisor;
            }
        }

        return substr($s, 0, -2);
}

