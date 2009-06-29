<?PHP
 
/* 
 * SoxBot Anti Testing Bot
 * Copyright (C) 2009 X! (soxred93 _at_ gmail _dot_ com)
 *
 * This program is free software; you can redistribute it and/or modify 
 * it under the terms of the GNU General Public License as published by 
 * the Free Software Foundation; either version 2 of the License, or 
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful, but 
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY 
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License 
 * for more details.
 * 
 * You should have received a copy of the GNU General Public License along 
 * with this program; if not, write to the Free Software Foundation, Inc., 
 * 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */
 	//INCLUDES
	require('/home/soxred93/bots/soxbot-test/wikibot.classes.php');
	require('/home/soxred93/bots/soxbot-test/bot.config.php');
	require('/home/soxred93/bots/soxbot-test/scorelist.php');
	require('/home/soxred93/bots/soxbot-test/functions.php');
 
 
	//DEFINES
	$version = '2.2';
	$pids = array();
	define( 'SIGNATURE', '[[User:SoxBot III|SoxBot III]] ([[User talk:SoxBot III|talk]] | [[User:X!|owner]])' );
	define( 'SCORELIMIT', -10 );
	declare(ticks = 1);
	
	if( $argv[1] == "debug" ) { define( 'DEBUG', 1 ); } else { define( 'DEBUG', '0' ); }
	$http   = new http;
	$wpapi	= new wikipediaapi;
	$wpq	= new wikipediaquery;
	$wpi	= new wikipediaindex;
	$wpapi->login($user,$pass);
 
	$simplewpapi	= new wikipediaapi;
	$simplewpapi->apiurl = 'http://simple.wikipedia.org/w/api.php';
	$simplewpq	= new wikipediaquery;
	$simplewpq->queryurl = 'http://simple.wikipedia.org/w/query.php';
	$simplewpapi->login($user,$pass);
 
	$whitelist = $wpq->getpage('User:'.$user.'/Whitelist');
 
	$trustedusers = array(
		'wikipedia/Juliancolton',
		'wikimedia/PeterSymonds',
		'wikipedia/Soxred93',
		'wikipedia/FastLizard4',
		'wikimedia/Thehelpfulone',
		'wikipedia/Stwalkerster',
	);
	
	$badtemplates = array(
 		'sofixit',
 		'done',
 	);
 	
 	$triggercharacters = array( '!', '@', '~', '.', '#', '$', '%', '^', '&', '*', '?' );
 	$holycraplongregex = '/^\[\[((Talk|User|Wikipedia|Image|MediaWiki|Template|Help|Category|Portal|Special)(( |_)talk)?:)?([^\x5d]*)\]\] (\S*) (http:\/\/en\.wikipedia\.org\/w\/index\.php\?diff=(\d*)&oldid=(\d*)|http:\/\/en\.wikipedia\.org\/wiki\/\S+)? \* ([^*]*) \* (\(([^)]*)\))? (.*)$/S';
 	
	pcntl_signal(SIGINT,   "sig_handler");
	pcntl_signal(SIGCHLD,   "sig_handler");
	pcntl_signal(SIGTERM,   "sig_handler");
 
 
	//PARSE CHANNELS
	$ircconfig = explode("\n",$wpq->getpage('User:'.$owner.'/Channels.js'));
	
	$tmp = array();
	foreach ( $ircconfig as $tmpline ) { 
		if ( substr( $tmpline,0,1 ) != '#' ) { //Ignore comments
			$tmpline = explode( '=',$tmpline,2 ); 
			$tmp[trim( $tmpline[0] )] = trim($tmpline[1]); 
		}
	}
	
	$ircchannel = $tmp['ircchannel'];
	$irctechchannel = $tmp['irctechchannel'];
	$ircverbosechannel = $tmp['ircverbosechannel'];
	$ircotherchannels = $tmp['ircotherchannels'];
	$ircvandalismchannel = $tmp['ircvandalismchannel'];
	$ircaivchannel = $tmp['ircaivchannel'];
	$irclogchannels = $tmp['irclogchannels'];
	$ircwikilinkchannels = $tmp['ircwikilinkchannels'];
	$ircpeakchannels = $tmp['ircpeakchannels'];
	
	$channels = array($ircchannel,$irctechchannel,$ircotherchannels,$ircverbosechannel,$ircvandalismchannel,$ircaivchannel,$irclogchannels,$ircwikilinkchannels,$ircpeakchannels);
	unset($tmp,$tmpline);
	
	//PARSE STALKING
	$stalk = array();
	$edit = array();
	
	//Start with stalking users...
	$tmp = explode("\n",$wpq->getpage('User:'.$user.'/Autostalk.js'));
	foreach ( $tmp as $tmp2 ) { 
		if (substr($tmp2,0,1) != '#') { 
			$tmp3 = explode('|',$tmp2,2); 
			$stalk[$tmp3[0]] = trim($tmp3[1]); 
		} 
	}
	
	//Now with stalking pages...
	$tmp = explode("\n",$wpq->getpage('User:'.$user.'/Autoedit.js'));
	foreach ( $tmp as $tmp2 ) { 
		if (substr($tmp2,0,1) != '#') { 
			$tmp3 = explode('|',$tmp2,2); 
			$edit[$tmp3[0]] = trim($tmp3[1]); 
		} 
	}
	
	//And finish with an unset...
	unset($tmp,$tmp2,$tmp3);
	
	//FILTER
	//We use array_unique and filter here because there are often duplicate entries
	$channels = array_filter(array_unique(array_merge($channels,$stalk,$edit)));
	
	//Now to split out commas in leftover channels
	$c = array();
	foreach( $channels as $chan ) {
		$chan = explode(',',$chan);
		foreach( $chan as $ch ) {
			$c[] = strtolower($ch);
		}
	}
	
	$channels = array_filter(array_unique($c));
	echo implode(', ', $channels);
	
	//DEBUGGING, so it doesn't take 30 seconds when I'm starting and stopping the bot constantly
	if( DEBUG == 1 ) {
		$channels = array( '##juliancolton' );
	}
 

	//CONNECT TO MYSQL
	$enwiki_mysql = mysql_connect( "enwiki-p.db.toolserver.org",$databaseuser,$databasepass,/* Force reconnect --> */ true );
	@mysql_select_db( "enwiki_p", $enwiki_mysql ) or die( "MySQL error: " .mysql_error() );

	$mysql = mysql_connect( "sql",$mysqluser,$mysqlpass );
	@mysql_select_db( $mysqldb, $mysql ) or die( "MySQL error: " .mysql_error() );
 
 
	//CONNECT TO PROXY
	$irc = fsockopen($ircserver,$ircport,$ircerrno,$ircerrstr,15);
 
	//FIRST FORK
	$ircpid = pcntl_fork();
	$pids[$ircpid] = $ircpid;
	
	if ($ircpid == 0) {
 
		//SET CONFIGS
		fwrite( $irc,'PASS '.$ircpass."\n" ); 
		fwrite( $irc,'USER '.$user.' "1" :SoxBot III (version '.$version.')'."\n" ); 
		fwrite( $irc,'NICK '.$user."\n" ); 
 
		//Do we still have the connection?
		while (!feof($irc)) {
		
			//Get the data
			$data = str_replace(array("\n","\r"),'',fgets($irc,1024));
 
			//Strip colors, it screws up the bot
			$data = preg_replace('/'.chr(3).'.{2,}/i','',$data);		
 
			echo 'IRC: '.$data."\n\n";
 
			/*
				Data for a privmsg:
				$d[0] = Nick!User@Host format.
				$d[1] = Action, e.g. "PRIVMSG", "MODE", etc. If it's a message from the server, it's the numerial code
				$d[2] = The channel somethign was spoken in
				$d[3] = The text that was spoken
			*/
			$d = $me = explode(' ',$data);
 
			//Get the plain text of the message
			//Why not use $me[3] here? Because $me[3] is only the text up to the first space
			unset($me[0], $me[1], $me[2]);
			$me = substr(implode(' ', $me),1);
 
			//Get the nick of the user
			$nick = substr($d[0],1);
			$nick = explode( '!', $nick );
			$nick = $nick[0];
 
			//Get the user's cloak
			$cloak = explode('@',$d[0]);
			$cloak = $cloak[1];
			if( !empty($cloak) ) echo "Cloak is $cloak.\n";
			
			//Because there's more than one SoxBot, it reads its own posts, causing endless loops
			if( $cloak == "wikipedia/soxred93/bot/SoxBot" ) continue;
 
			//Easier to recognize variable
			$chan = strtolower($d[2]);
 
			//Playing a game of ping pong with the server
			if (strtolower($d[0]) == 'ping') {
				fwrite( $irc,'PONG '.$d[1]."\n" ); 
			} 
			
			//Time to join the channels
			elseif ( ( $d[1] == '376' ) or ( $d[1] == '422' ) ) {
				foreach ($channels as $joinchan) {
					echo "\n\nJOINING $joinchan...\n\n";
					fwrite( $irc,'JOIN '.$joinchan."\n" ); 
					sleep(1);
				}
				unset( $joinchan );

				foreach (explode(',',$ircchannel) as $y) {
					fwrite( $irc,'PRIVMSG '.$y.' :IRC logging enabled.'."\n" ); 
				}
			}
			
			//Main message parser
			elseif ($d[1] == 'PRIVMSG') {
 
				//Wikilinker function
				if (preg_match_all('/\[\[(.*?)\]\]/', $me, $l) && !preg_match('/(\!shortpath|\!link)/i',$data)) { 
 
					if( !in_arrayi( $chan, explode( ',',$ircwikilinkchannels ) ) ) { 
						echo "Message in $chan is not in a channel I am allowed to wikilink.\n";
						continue; 
					}
 
					$i = 1;
					$links = array(); //Will store the full urls
					foreach($l[1] as $link) {
						if($i >= 5) break;
 
						//Deal with piped links
						if( strpos( $link, '|' ) !== false ) { 
							$link = explode('|', $link);
							$link = $link[0];
						}
 						
 						$link = str_replace('$1',$link,'http://en.wikipedia.org/wiki/$1');//Append url
						$link = str_replace(' ','_',$link);//Prevent encoding spaces
						$link = str_replace(array('[',']'),'',$link);//Remove illegal characters
						$link = urlencode($link);//Urlencode it
						$link = str_replace('%2F','/',$link);//Handle subpages cleanly
						$link = str_replace('%3A',':',$link);//Handle subpages cleanly
						
						$links[] = $link;
						$i++;
					}
					
					$links = implode( ', ', $links);
					
					if( stripos($chan, "##juliancolton") !== false && 1 == 2 ) {//Disabled... 1 is never equal to 2
						fwrite( $irc,'PRIVMSG '.$d[2].' :Yeah, bitches, here\'s the links! '.$links."\n" );
					}
					else {
						fwrite( $irc,'PRIVMSG '.$d[2].' :'.$nick.': '.$links."\n" );
					}
				}
 
				//Template linker function
				if (preg_match_all('/\{\{(.*?)\}\}/', $me, $l) && !preg_match('/(\!shortpath|\!link)/i',$data)) { 
 
					if( !in_arrayi( $chan, explode( ',',$ircwikilinkchannels ) ) ) { 
						echo "Message in $chan is not in a channel I am allowed to templatelink.\n";
						continue; 
					}
 
					$i = 1;
					$links = array(); //Will store the full urls
					foreach($l[1] as $link) {
						if($i >= 5) break;
 
						//Deal with piped links
						if( strpos( $link, '|' ) !== false ) { 
							$link = explode('|', $link);
							$link = $link[0];
						}
 						
 						$link = str_replace('$1',$link,'http://en.wikipedia.org/wiki/Template:$1');//Append url
 						$link = str_replace('subst:', '', $link);//Remove subst
						$link = str_replace(' ','_',$link);//Prevent encoding spaces
						$link = str_replace(array('[',']'),'',$link);//Remove illegal characters
						$link = urlencode($link);//Urlencode it
						$link = str_replace('%2F','/',$link);//Handle subpages cleanly
						$link = str_replace('%3A',':',$link);//Handle subpages cleanly
						
						$links[] = $link;
						$i++;
					}
					
					$links = implode( ', ', $links);
					
					if( stripos($chan, "##juliancolton") !== false && 1 == 2 ) {//Disabled... 1 is never equal to 2
						fwrite( $irc,'PRIVMSG '.$d[2].' :Yeah, bitches, here\'s the links! '.$links."\n" );
					}
					else {
						fwrite( $irc,'PRIVMSG '.$d[2].' :'.$nick.': '.$links."\n" );
					}
				}
 
				//Main function parser
				if ( in_array( substr($me,0,1), $triggercharacters ) ) {
					$command = explode(' ',substr(strtolower($me),1));
					$command = $command[0];
 
					echo "Got a command! The command is: ".$command."\n";
 
					//List of accepted commands
					$commands = array( 
						'lastedit', 
						'stalk', 
						'status', 
						'c',
						'count', 
						'eval', 
						'amsg', 
						/* 'die', */
						/*'restart', */ //die and restart are broken right now
						'maxlag', 
						'replag', 
						'setspeak',
						'speak',
						'soxbotcounts', 
						'cluebotcounts',
						'commands', 
						'version',
						'shortpath',
						'link',
						'h',
						'hits',
						'staff',
						'namesadd',
						'namesdel',
						'rfxupdate',
						'ip2name',
						'name2ip',
						'dns',
						'rdns',
						'rand',
					);
 
					//Don't do anything if the command doesn't even exist
					if( !in_arrayi( $command, $commands ) ) {
						echo "Command not allowed.\n";
						continue;
					}			
 
 					//PRIVMSG command, just a shortcut
					$cmd = 'PRIVMSG '.$chan;
 
					//Get the parameters
					$param = explode(' ', $me);
					unset($param[0]);
					$param = implode(' ', $param);
					$param = trim($param);
 
					echo "Params: $param\n";
 
					//Different functions for different commands
					switch ($command) {
						case 'lastedit':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
							if (preg_match("/\[\[(.*)\]\]/",$param,$m)) {
								$param = $m[1];
								unset($m);
							}
 
							$rv = $wpapi->revisions($param,1,'older');
							if( $rv[0]['user'] ) {
								fwrite($irc,$cmd.' :'.$r2.' http://en.wikipedia.org/w/index.php?title='.urlencode($r2).'&diff=prev' .
									'&oldid='.urlencode($rv[0]['revid']).' * '.$rv[0]['user'].' * '.$rv[0]['comment']."\n");
							}
							unset($rv);
							break;
						case 'stalk':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
							if (preg_match("/\[\[User:(.*)\]\]/",$param,$m)) {
								$param = $m[1];
								unset($m);
							}
 
							$uc = $wpapi->usercontribs($param,1);
							if( $uc[0]['title'] ) {
								fwrite($irc,$cmd.' :[['.$uc[0]['title'].']] http://en.wikipedia.org/w/index.php?title='.urlencode($uc[0]['title']).'&diff=prev' .
									'&oldid='.urlencode($uc[0]['revid']).' * '.$r2.' * '.$uc[0]['comment']."\n");
							}
							break;
						case 'status':
							//Get titles bot has reverted, and remove ones older than 24 hours
							$titles = unserialize(file_get_contents('/home/soxred93/bots/soxbot-test/titles.txt'));
							foreach ($titles as $title => $time) {
								if ((time() - $time) > (24*60*60)) {
									unset($titles[$title]);
								}
							}
							file_put_contents('/home/soxred93/bots/soxbot-test/titles.txt',serialize($titles));
							$count = count($titles);
							unset($titles);
 
							//Is bot enabled?							
							if (!preg_match('/(yes|enable|true)/i',$wpq->getpage('User:'.$user.'/Run'))) {
								$run = false;
							} else {
								$run = true;
							}
 
							fwrite( $irc,$cmd.' :I am '.$user.'.  I am currently '.($run?'enabled':'disabled').'.  I currently have '.$wpq->contribcount($user).' contributions.'."\n" ); sleep(1);
							fwrite( $irc,$cmd.' :I am currently running version '.$version.' of SoxBot Anti-Testing Bot.'."\n" ); sleep(1);
							fwrite( $irc,$cmd.' :I have attempted to revert '.$count.' unique article/user combinations in the last 24 hours. '."\n" ); sleep(1);
							fwrite( $irc,$cmd.' :I have attempted to revert '.($wpq->contribcount($user) - 5341).' edits throughout my lifetime. '."\n" ); sleep(1);
							fwrite( $irc,$cmd.' :I log all information to '.$ircchannel.'.  This channel is '.$chan.".\n" ); 
 
							unset($count,$run,$time);
							break;
						case 'c':
						case 'count':
								
							//Reconnect if it has dropped the connection
							if (!mysql_ping($mysql)) { 
								include_once('/home/soxred93/database.inc');
								$mysql = mysql_connect( "sql","soxred93",$toolserver_password,true );
								echo "\n\n".mysql_error()."\n\n";
								var_dump($mysql);
								if( !$mysql ) die( mysql_error() );
								mysql_select_db( "u_soxred93", $mysql ) or die( "MySQL error: " .mysql_error() );
							}
							
							//Get the aliases of certain users
							$result = mysql_query( "SELECT * FROM names", $mysql );
							$contents = array();
							while( $row = mysql_fetch_assoc( $result ) ) {
								$contents[ $row['nick'] ] = $row['user'];
							}
 							
 							//First see if there's no param at all, which implies that they want their own count
							if( !$param || $param == '' ) { 
							
								//Check if it's in the known nicks
								if( isset($contents[$nick])) { 
									$param = $contents[$nick];
								}
								
								//Check if it's a valid user, for users who use their WP name as their nick
								elseif( $wpapi->users($nick) ) {
									$param = $nick;
								}
								
								//Don't know? Ignore.
								else {
									fwrite( $irc,$cmd.' :Required parameter not given. Talk to X! if you want .count to work for your nick.'."\n" );continue; 
								}
							}
							
							//Now see if someone passed a nick as a param
							elseif( isset($contents[$param])) { 
								$param = $contents[$param];
							}
							
							$r3 = urlencode($param);
							$r3 = str_replace('+','_',$r3);
							
							if( $chan == "#wikipedia-simple" ) {
								$count = $simplewpq->contribcount($param);
								$r3 .= "/simple";
								fwrite( $irc,$cmd.' :'.$param.' has '.$count." contributions. For more info, see http://toolserver.org/~soxred93/ec/$r3 \n" ); 
								break;
							}
							
							//Make names match those in the database
							$param = str_replace('_', ' ', $param);
							$param = ucfirst( $param );
							
							if (!mysql_ping($enwiki_mysql)) { 
								$enwiki_mysql = mysql_connect("enwiki-p.db.toolserver.org",$databaseuser,$databasepass,/* Force reconnect --> */ true);
								@mysql_select_db("enwiki_p", $enwiki_mysql) or die( "MySQL error: " .mysql_error() );
							}
							
							$query = 'SELECT COUNT(*) AS count FROM archive WHERE ar_user_text = \''.mysql_real_escape_string($param).'\';';
							echo $query;
							$result = mysql_query( $query, $enwiki_mysql );
							var_dump($result);
							$row = mysql_fetch_assoc( $result );
							var_dump($row);
							$edit_count_deleted = $row['count'];
							unset( $row, $query, $result );
							
							if (!mysql_ping($enwiki_mysql)) { 
								$enwiki_mysql = mysql_connect("enwiki-p.db.toolserver.org",$databaseuser,$databasepass,/* Force reconnect --> */ true);
								@mysql_select_db("enwiki_p", $enwiki_mysql) or die( "MySQL error: " .mysql_error() );
							}
							
							$query = 'SELECT COUNT(*) AS count FROM revision WHERE rev_user_text = \''.mysql_real_escape_string($param).'\';';
							echo $query;
							$result = mysql_query( $query, $enwiki_mysql );
							var_dump($result);
							$row = mysql_fetch_assoc( $result );
							var_dump($row);
							$edit_count_live = $row['count'];
							unset( $row, $query, $result );
							
							$edit_count_total = $edit_count_live + $edit_count_deleted;
 							
 							fwrite( $irc,$cmd.' :'.$param.' has '.$edit_count_total." total contributions, $edit_count_live live edits, and $edit_count_deleted deleted edits. For more info, see http://toolserver.org/~soxred93/ec/$r3 \n" ); 
							break;
						case 'soxbotcounts':

							$b = array( 
								'SoxBot',
								'SoxBot II',
								'SoxBot III',
								'SoxBot IV',
								'SoxBot V',
								'SoxBot VI',
								'SoxBot VII',
								'SoxBot VIII',
								'SoxBot IX',
								'SoxBot X',
								'MPUploadBot'
							);
 
							$ms = '';
							$count = 0;
							foreach( $b as $bot ) {
								$count += $wpq->contribcount($bot);
								$ms .= "\002" . $bot . "\002 -> " . $wpq->contribcount($bot) . ". ";
							}
							$ms .= "\002" . 'Total' . "\002 -> " . $count . ". ";
							fwrite( $irc,$cmd.' :'.$ms." \n" ); 
							unset($b, $bots, $bot, $ms);
							break;
						case 'cluebotcounts':

							$b = array( 'ClueBot', 'ClueBot II', 'ClueBot III', 'ClueBot IV', 'ClueBot V', 'ClueBot VI');
 
							$ms = '';
							$count = 0;
							foreach( $b as $bot ) {
								$count += $wpq->contribcount($bot);
								$ms .= "\002" . $bot . "\002 -> " . $wpq->contribcount($bot) . ". ";
							}
							$ms .= "\002" . 'Total' . "\002 -> " . $count . ". ";
							fwrite( $irc,$cmd.' :'.$ms." \n" ); 
							unset($b, $bots, $bot, $ms);
							break;
						case 'maxlag':
							$ml = $wpi->getMaxlag();
							fwrite( $irc,$cmd.' :Current maxlag level: '.$ml[2]." seconds lagged. Server: ".$ml[1].". \n" ); 
							break;
						case 'commands':
							fwrite( $irc,$cmd.' :Allowed commands: '.implode(', ', $commands).". \n" ); 
							break;
						case 'eval':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
							$code = $param;
							if ($cloak == $ownercloak) {
								$result = eval($code);
								if( $result != '' && !$result ) {
									fwrite( $irc,$cmd.' :Eval result: "'.$result.'"'."\n" ); 
								}
								unset($code,$result);
							} else {
								fwrite( $irc,$cmd.' :eval can only be used by '.$ownercloak.'.'."\n" ); 
							}
							break;
						case 'amsg':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
							if (in_array($cloak, $trustedusers)) {
								foreach( $channels as $ch ) {
									fwrite( $irc,'PRIVMSG '.$ch.' :Message from '.$nick.': '.$param."\n" );
									sleep(1);
								}
 
							}
							else {
								fwrite( $irc,$cmd.' :amsg can only be used by trusted members.'."\n" ); 
							}
							break;
						case 'die':
							if ($cloak == $ownercloak) {
								//Edit cron to stop the automatic restarting of the bot
								$crontab = shell_exec('crontab -l');
								file_put_contents('/home/soxred93/bots/soxbot-test/crontab', preg_replace('/(.*) (.*) \* \* \* \/usr\/local\/bin\/phoenix/', '#\1 \2 * * * /usr/local/bin/phoenix', $crontab));
								shell_exec('crontab /home/soxred93/bots/soxbot-test/crontab');
								posix_kill(posix_getppid(), SIGTERM);
								if( posix_get_last_error() != 0 ) {
				 					fwrite( $irc,$cmd.' :'.posix_strerror(posix_get_last_error())."\n" );
								}
								else {
									fwrite( $irc,$cmd.' :If you say so.'."\n" );
								}
							}
							else {
								fwrite( $irc,$cmd.' :die can only be used by '.$ownercloak.'.'."\n" );  
							}
							break;
						case 'restart':
							if (in_array($cloak, $trustedusers)) {
								posix_kill(posix_getppid(), SIGTERM);
				 				if( posix_get_last_error() != 0 ) {
				 					fwrite( $irc,$cmd.' :'.posix_strerror(posix_get_last_error())."\n" );
								}
								else {
									fwrite( $irc,$cmd.' :If you say so.'."\n" );
								}
							}
							else {
								fwrite( $irc,$cmd.' :restart can only be used by trusted members.'."\n" );
							}
							break;
						case 'namesadd':
							if (in_array($cloak, $trustedusers)) {
								$param = explode(' ',$param);
								
								//Check if the connection is still there
								if (!mysql_ping($mysql)) { 
									include_once('/home/soxred93/database.inc');
									$mysql = mysql_connect( "sql","soxred93",$toolserver_password,true );
									@mysql_select_db( "u_soxred93", $mysql ) or die( "MySQL error: " .mysql_error() );
								}

								mysql_query( "INSERT INTO names VALUES ('".mysql_real_escape_string($param[0])."', '".mysql_real_escape_string($param[1])."');", $mysql );
								fwrite( $irc,$cmd.' :Done!'."\n" );
							}
							else {
								fwrite( $irc,$cmd.' :namesadd can only be used by trusted members.'."\n" );
							}
							break;
						case 'namesdel':
							if (in_array($cloak, $trustedusers)) {
								$param = explode(' ',$param);
								
								//Check if the connection is still there
								if (!mysql_ping($mysql)) { 
									include_once('/home/soxred93/database.inc');
									$mysql = mysql_connect( "sql","soxred93",$toolserver_password,true );
									@mysql_select_db( "u_soxred93", $mysql ) or die( "MySQL error: " .mysql_error() );
								}
								
								mysql_query( "DELETE FROM names WHERE '".mysql_real_escape_string($param[0])."' = nick;", $mysql );
								fwrite( $irc,$cmd.' :Done!'."\n" );
							}
							else {
								fwrite( $irc,$cmd.' :namesdel can only be used by trusted members.'."\n" );
							}
							break;
						case 'replag':
							$servers = array( 
								array( 
									'display' => 's1',
									'serv' => 'sql-s1',
									'db' => 'enwiki_p'
								),
								array( 
									'display' => 's1-c',
									'serv' => 'sql-s1',
									'db' => 'commonswiki_p'
								),
								array( 
									'display' => 's2',
									'serv' => 'sql-s2',
									'db' => 'dewiki_p'
								),
								array( 
									'display' => 's3',
									'serv' => 'sql-s3',
									'db' => 'frwiki_p'
								),
								array( 
									'display' => 's3-c',
									'serv' => 'sql-s3',
									'db' => 'commonswiki_p'
								)
							);
							$r = array();
							$msg = "Replag: ";
							foreach( $servers as $serv ) {
								$display = $serv['display'];
								$domain = $serv['serv'];
								$db = $serv['db'];
								$mysql = mysql_connect($domain.':'.$databaseport,$databaseuser,$databasepass,/* Force reconnect --> */ true);
								mysql_select_db($db, $mysql);
 
        						$result = mysql_query( "SELECT UNIX_TIMESTAMP() - UNIX_TIMESTAMP(rc_timestamp) as replag FROM recentchanges ORDER BY rc_timestamp DESC LIMIT 1" );
        						if( !$result ) fwrite( $irc,$cmd.' :Couldn\'t get a result.'."\n" );
        						$row = mysql_fetch_assoc( $result );
        						$secs = $row['replag'];
 
								$msg .= "\002" . $display . "\002 -> ";
 
								if( $secs > ( 24 * 60 * 60 ) ) {
									$msg .= "\00305"; 
								}
								elseif( $secs > ( 60 * 60 ) ) {
									$msg .= "\00308"; 
								}
								else {
									$msg .= "\00302"; 
								}
								
								$msg .= secs2str($secs);
								$msg .= "\003 / ";
							}
							
							$mysql = mysql_connect( "sql",$mysqluser,$mysqlpass );
							@mysql_select_db( $mysqldb, $mysql );
 
							fwrite( $irc,$cmd.' :'.$msg."\n" );
							unset($msg,$replag,$servers,$row,$minute,$hour,$second,$day,$week,$secs);
							break;
						case 'setspeak':
							if ($cloak == $ownercloak) {
								file_put_contents('/home/soxred93/bots/soxbot-test/channel', trim($param));	
								fwrite( $irc,$cmd.' :Done!'."\n" );
							}
							else {
								fwrite( $irc,$cmd.' :setspeak can only be used by '.$ownercloak.'.'."\n" );
							}
							break;
						case 'speak':
							if ($cloak == $ownercloak) {	
								fwrite( $irc,'PRIVMSG '.file_get_contents('/home/soxred93/bots/soxbot-test/channel').' :'.$param."\n" );
							}
							else {
								fwrite( $irc,$cmd.' :speak can only be used by '.$ownercloak.'.'."\n" );
							}
							break;
						case 'link':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
							if (preg_match('/\[\[(.*?)\]\]/', $data, $l)) {
								$links = $l[1];
								if( strpos( $links, '|' ) !== false ) { 
									$links = explode('|', $links);
									$links = $links[0];
								}
 
								$links = str_replace(' ','_',$links);//Prevent encoding spaces
								$links = str_replace(array('[',']'),'',$links);//Remove illegal characters
								$links = urlencode($links);
								$links = str_replace('%2F','/',$links);//Handle subpages cleanly
 
								fwrite( $irc,$cmd.' :'.$nick.': http://en.wikipedia.org/wiki/'.$links."\n" );
								usleep(500);
							}
							elseif (preg_match_all('/\{\{(.*?)\}\}/', $data, $l)) {
								$links = $l[1];
								if( strpos( $links, '|' ) !== false ) { 
									$links = explode('|', $links);
									$links = $links[0];
								}
 
								$links = str_replace(' ','_',$links);//Prevent encoding spaces
								$links = str_replace(array('{','}'),'',$links);//Remove illegal characters
								$links = urlencode($links);
								$links = str_replace('%2F','/',$links);//Handle subpages cleanly
 
								fwrite( $irc,$cmd.' :'.$nick.': http://en.wikipedia.org/wiki/Template:'.$links."\n" );
								usleep(500);
							}
							else {
								fwrite( $irc,$cmd.' :Couldn\'t find link.'." \n" ); 
							}
							break;
						case 'version':
							fwrite( $irc,$cmd.' :I am currently running version '.$version.' of SoxBot Anti-Testing Bot.'."\n" ); 
							break;
						case 'shortpath':
							$param = preg_match('/\[\[(.*)\]\] \|\| \[\[(.*)\]\]/', $me, $arts);
							if( count($arts) < 3 ) {
								fwrite( $irc,$cmd.' :Not enough parameters.'."\n" ); 
								continue;
							}
							$results = $http->get('http://www.netsoc.tcd.ie/~mu/cgi-bin/shortpath.cgi?from='.urlencode($arts[1]).'&to='.urlencode($arts[2]));
							$articles = preg_match_all('/\<a href=\"http:\/\/en\.wikipedia\.org\/wiki\/(.*?)\"\>(.*?)\<\/a\>/',$results,$m);
							$articles = $m[2];
							$count = (count($articles)-1);
							if( $count >= 0 ) {
								fwrite( $irc,$cmd.' :It takes '.$count.' clicks to get from '.$arts[1].' to '.$arts[2].'.'."\n" ); 
							}
							else {
								fwrite( $irc,$cmd.' :Article was created before the last database dump.'."\n" ); 
								fwrite( $irc,$cmd.' :Taken from http://www.netsoc.tcd.ie/~mu/cgi-bin/shortpath.cgi?from='.urlencode($arts[1]).'&to='.urlencode($arts[2]).'.'."\n");
								continue;
							}
							if( count($articles) < 9 ) { fwrite( $irc,$cmd.' :Articles: '.implode(" --> ", $articles)."\n" ); } 
							break;
						case 'h':
						case 'hits':
							
							//Reconnect if it has dropped the connection
							if (!mysql_ping($mysql)) { 
								include_once('/home/soxred93/database.inc');
								$mysql = mysql_connect( "sql","soxred93",$toolserver_password,true );
								@mysql_select_db( "u_soxred93", $mysql ) or die( "MySQL error: " .mysql_error() );
							}
							
							//Get the aliases of certain pages
							$result = mysql_query( "SELECT * FROM names" );
							$contents = array();
							while( $row = mysql_fetch_assoc( $result ) ) {
								$contents[ $row['nick'] ] = $row['user'];
							}
 							
 							//First see if there's no param at all, which implies that they want their own user talk page
							if( !$param || $param == '' ) { 
							
								//Check if it's in the known nicks
								if( isset($contents[$nick])) { 
									$param = "User talk:".$contents[$nick];
								}
								
								//Check if it's a valid user, for users who use their WP name as their nick
								elseif( $wpapi->users($nick) ) {
									$param = "User talk:".$nick;
								}
								
								//Don't know? Ignore.
								else {
									fwrite( $irc,$cmd.' :Required parameter not given. Talk to X! if you want .hits to work for your nick.'."\n" );continue; 
								}
							}
							
							//Now see if someone passed a nick as a param
							elseif( isset($contents[$param])) { 
								$param = "User talk:".$contents[$param];
							}							
							
							if( $chan == "#wikipedia-simple" ) {
								$simplehits = 'simple';
							}
							else {
								$simplehits = 'en';
							}
							
							$r3 = urlencode($param);
							$r3 = str_replace('+','_',$r3);
							$r3 = str_replace('%2F','/',$r3);
							$results = $http->get('http://stats.grok.se/json/'.$simplehits.'/'.date("Ym").'/'.$r3);
							$json = json_decode($results);
							
							if($json) {
								fwrite( $irc,$cmd.' :'.$json->{'title'}.' has been viewed '.$json->{'total_views'}.' times in '.$json->{'month'}.". See ".'http://stats.grok.se/'.$simplehits.'/'.date("Ym").'/'.$r3."\n" ); 
							}
							else {
								fwrite( $irc,$cmd.' :Couldn\'t find hit count.'."\n" );
							}
							unset($r3);
							break;
						case 'rfxupdate':
							if (in_array($cloak, $trustedusers)) {
								echo "Updating...\n";
								echo shell_exec("/usr/bin/php /home/soxred93/bots/rfx-report.php");
								echo shell_exec("/usr/bin/php /home/soxred93/bots/rfx-tally.php");
								fwrite( $irc,$cmd.' :Done!'."\n" );
							}
							else {
								fwrite( $irc,$cmd.' :rfxupdate can only be used by trusted members.'."\n" );
							}
							break;
						case 'ip2host':
						case 'rdns':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
 
							$rdns = gethostbyaddr($param);
 
							if( $rdns == $param ) {
								fwrite( $irc,$cmd.' :Not found.'."\n" );continue;
							}
 							
							fwrite( $irc,$cmd.' :Result: '.$rdns."\n" );continue;
							break;
						case 'host2ip':
						case 'dns':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
 
							$dns = gethostbyname($param);
 
							if( $dns == $param ) {
								fwrite( $irc,$cmd.' :Not found.'."\n" );continue;
							}
 							
							fwrite( $irc,$cmd.' :Result: '.$dns."\n" );continue;
							break;
						case 'rand':
							if( !$param || $param == '' ) { fwrite( $irc,$cmd.' :Required parameter not given.'."\n" );continue; }
							
 							$rand = explode( ' ', $param );
 							$rand = mt_rand($rand[0],$rand[1]);
 							if( $rand[1] > 20 ) break;
 							echo "Random number: $rand\n";
 							fwrite( $irc,$cmd.' :Result: '.$rand."\n" );
 							break;
					}
				}
			}
		}
		posix_kill(posix_getppid(), SIGTERM);
	}
 
	$run = $wpq->getpage('User:'.$user.'/Run');
 
	//Start IRC feed parser
	while (1) {
		$feed = fsockopen($feedhost,$feedport,$feederrno,$feederrstr,30);
 
		if (!$feed) {
			sleep(10);
			$feed = fsockopen($feedhost,$feedport,$feederrno,$feederrstr,30);
			if (!$feed) die($feederrstr.' ('.$feederrno.')');
		}
 
		fwrite($feed,'USER '.$user.' "1" "1" :SoxBot Wikipedia Bot.'."\n");
		fwrite($feed,'NICK '.$user."\n");
 
		while (!feof($feed)) {
			$rawline = fgets($feed,1024);
			$line = str_replace(array("\n","\r","\002"),'',$rawline);
			$line = preg_replace('/\003(\d\d?(,\d\d?)?)?/','',$line);
			//echo 'FEED: '.$line."\n";
			if (!$line) { fclose($feed); break; }
			$linea= explode(' ',$line,4);
 
			if (strtolower($linea[0]) == 'ping') {
				fwrite($feed,'PONG '.$linea[1]."\n");
			} elseif (($linea[1] == '376') or ($linea[1] == '422')) {
				fwrite($feed,'JOIN '.$feedchannel."\n");
			} elseif ((strtolower($linea[1]) == 'privmsg') and (strtolower($linea[2]) == strtolower($feedchannel))) {
				$message = substr($linea[3],1);
				if (preg_match($holycraplongregex,$message,$m)) {
					$messagereceived = microtime(1);
					$change['namespace'] = $m[1];
					$change['title'] = $m[5];
					$change['flags'] = $m[6];
					$change['url'] = $m[7];
					$change['revid'] = $m[8];
					$change['old_revid'] = $m[9];
					$change['user'] = $m[10];
					$change['length'] = $m[12];
					$change['comment'] = $m[13];
 
//					include 'cluebot.stalk.config.php';
					$pos = strpos($change['flags'], 'B');
 					if ($pos !== false) continue;
 					
					$stalkchannel = array();
					foreach ($stalk as $key => $value) if (fnmatch(str_replace('_',' ',$key),str_replace('_',' ',$change['user']))) $stalkchannel = array_merge($stalkchannel,explode(',',$value));
					foreach ($edit as $key => $value) if (fnmatch(str_replace('_',' ',$key),str_replace('_',' ',$change['namespace'].$change['title']))) $stalkchannel = array_merge($stalkchannel,explode(',',$value));
//					if ($change['user'] == $owner) $stalkchannel[] = $ircchannel;
 
					$stalkchannel = array_unique($stalkchannel);
 
					foreach ($stalkchannel as $y) {
						fwrite($irc,'PRIVMSG '.$y.' :New edit: [['.$change['namespace'].$change['title'].']] http://en.wikipedia.org/w/index.php?diff=prev'.'&oldid='.urlencode($change['revid']).' * '.$change['user'] .
							' * '.$change['comment']."\n");
						sleep(1);
					}
 
					//Update $stalk, $edit, and $channels variables
					if (($change['namespace'] == 'User:')) {
						if (strtolower($change['title']) == strtolower($user.'/Run')) { $run = $wpq->getpage('User:'.$user.'/Run'); }
						if (strtolower($change['title']) == strtolower($user.'/Whitelist')) { $whitelist = $wpq->getpage('User:'.$user.'/Whitelist'); }
						if (strtolower($change['title']) == strtolower($user.'/Autostalk.js')) {
							fwrite( $irc,'PART '.implode(',',$stalk)." Parting due to ".$change['user']." editing User:".$user.'/Autostalk.js'."\n" );
							sleep(2);
							unset($stalk);
							$tmp = explode("\n",$wpq->getpage('User:'.$user.'/Autostalk.js'));
							foreach ($tmp as $tmp2) { if (substr($tmp2,0,1) != '#') { $tmp3 = explode('|',$tmp2,2); $stalk[$tmp3[0]] = trim($tmp3[1]); } }
							unset($tmp,$tmp2,$tmp3);
							print_r($stalk);
							fwrite( $irc,'JOIN '.implode(',',$stalk)."\n" );
							sleep(2);
						}
						echo $change['title'];
						if (strtolower($change['title']) == strtolower($user.'/Autoedit.js')) {
							fwrite( $irc,'PART '.implode(',',$edit)." Parting due to ".$change['user']." editing User:".$user.'/Autoedit.js'."\n" );
							sleep(2);
							unset($edit);
							$tmp = explode("\n",$wpq->getpage('User:'.$user.'/Autoedit.js'));
							foreach ($tmp as $tmp2) { if (substr($tmp2,0,1) != '#') { $tmp3 = explode('|',$tmp2,2); $edit[$tmp3[0]] = trim($tmp3[1]); } }
							unset($tmp,$tmp2,$tmp3);
							print_r($edit);
							fwrite( $irc,'JOIN '.implode(',',$edit)."\n" );
							sleep(2);
						}
						if (strtolower($change['title']) == strtolower($owner.'/Channels.js')) {
							$ircconfig = explode("\n",$wpq->getpage('User:'.$owner.'/Channels.js'));
							$tmp = array();
							foreach($ircconfig as $tmpline) { if (substr($tmpline,0,1) != '#') { $tmpline = explode('=',$tmpline,2); $tmp[trim($tmpline[0])] = trim($tmpline[1]); } }
							print_r($tmp);
 
							$tmpold = array();
							$tmpnew = array();
 
							foreach ($tmp as $tmp2) foreach (explode(',',$tmp2) as $tmp3) $tmpnew[$tmp3] = 1;
							foreach (explode(',',$ircchannel.','.$irctechchannel.','.$ircotherchannels.','.$ircvandalismchannel.','.$ircaivchannel.','.$ircverbosechannel.','.$irclogchannels.','.$ircwikilinkchannels.','.$ircpeakchannels) as $tmp3) $tmpold[$tmp3] = 1;
 
							foreach ($tmpold as $tmp2 => $tmp3) if (isset($tmpnew[$tmp2])) unset($tmpold[$tmp2],$tmpnew[$tmp2]);
							foreach ($tmpnew as $tmp2 => $tmp3) $tmpnew1[] = $tmp2;
							foreach ($tmpold as $tmp2 => $tmp3) $tmpold1[] = $tmp2;
 
							$tmpold = $tmpold1; $tmpnew = $tmpnew1; unset($tmpold1,$tmpnew1);
 
							fwrite( $irc,'JOIN '.implode(',',$tmpnew)."\n" ); 
							fwrite( $irc,'PART '.implode(',',$tmpold)." Parting due to ".$change['user']." editing User:".$owner.'/Channels.js'."\n" ); 
 
							$ircchannel = $tmp['ircchannel'];
							$irctechchannel = $tmp['irctechchannel'];
							$ircverbosechannel = $tmp['ircverbosechannel'];
							$ircotherchannels = $tmp['ircotherchannels'];
							$ircvandalismchannel = $tmp['ircvandalismchannel'];
							$ircaivchannel = $tmp['ircaivchannel'];
							$irclogchannels = $tmp['irclogchannels'];
							$ircwikilinkchannels = $tmp['ircwikilinkchannels'];
							$ircpeakchannels = $tmp['ircpeakchannels'];
 
							unset($tmp,$tmpline,$tmpold,$tmpnew,$tmp2,$tmp3);
						}
					}
 
					if (($change['namespace'] != '') || ($change['flags'] == 'move')) continue; //Ignore moves and nonmainspace edits
 
					//Add namespace to the title
					$change['title'] = $change['namespace'].$change['title'];
 
					//Don't bother with forking if it is disabled
					if (!preg_match('/(yes|enable|true)/i',$run)) {
						continue;
					}
 
					//Start the fork!!!
					$feedpid = @pcntl_fork();
					//$pids[$feedpid] = $feedpid;
					if ($feedpid != 0) continue;
					//echo posix_getpid()."\n";
 
					$diff = $wpi->diff($change['title'],$change['old_revid'],$change['revid']);//Get diff
					$score = score($testlist,$diff[0],$log);//Match edit against $scorelist
					$score -= score($testlist,$diff[1],$log2);//Add points for each instance removed.
					$url = 'http://en.wikipedia.org/w/index.php?title='.urlencode($change['title']).'&diff='.urlencode($change['revid']).'&oldid='.urlencode($change['old_revid']);//Url of the diff
 
					$tmp = unserialize(file_get_contents('/home/soxred93/bots/soxbot-test/titles.txt'));
 
					if (
						($score <= SCORELIMIT)
						&& (!preg_match('/\<code\>/', $wpq->getpage($change['title']))) //Ignore pages with <code>
						&& ((($wpq->contribcount($change['user']) < 50) || preg_match('/(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/', $change['user'])))//Ignore accounts with >50 edits
						&& (!preg_match('/^\* \[\[User:('.preg_quote($change['user'],'/').')|\1\]\] \- .*/',$whitelist)) //Ignore users listed on User:SoxBot III/Whitelist 
						&& ((!isset($tmp[$change['title'].$change['user']])) || ((time() - $tmp[$change['title'].$change['user']]) > (24*60*60)))//Ignore users and pages who were reverted in last 24 hours
						&& ($tmp[$change['title'].$change['user']] = time())
						&& ((file_put_contents('/home/soxred93/bots/soxbot-test/titles.txt',serialize($tmp))) !== false)
						&& (($change['length'] < 600) || ($score <= -35))//Make threshold for reverting higher if the changed length is high
					) { 
						//Get maxlag, and don't revert if it is high.
						$curmaxlag = $wpi->getMaxlag();
						if( $curmaxlag[2] > $maxlag ) {
							foreach(explode(',',$irctechchannel) as $y) {
								fwrite( $irc,'PRIVMSG '.$y.' :'.$curmaxlag[1].' is lagged out by '.$curmaxlag[2].' seconds. ('.$curmaxlag[0].')'."\n" ); 
								usleep(500);
							}
							continue;
						}
						unset($curmaxlag);
 
						//Notify in channel
						foreach (explode(',',$ircchannel) as $y) {
							fwrite( $irc,'PRIVMSG '.$y.' :Reverting revision http://en.wikipedia.org/wiki/?diff='.$change['revid'].' by '.$change['user'].'.'."\n" ); usleep(500);
						}
 
						//Remainder of $ircverbosechannel
						foreach (explode(',',$ircverbosechannel) as $y) {
							fwrite( $irc,'PRIVMSG '.$y.' :Reverting revision '.$change['revid'].' by '.$change['user'].'.'."\n" ); usleep(500);
						}
						unset($diff, $revision, $url);
 
						//Were we beaten?
						$currev = $wpapi->revisions($change['title'],3,'newer',false,$change['revid']);
						if (($currev[0]['revid'] != $change['revid']) && ($currev[0]['user'] != $change['user']) && $currev[0]['user']) {
							mysql_query('INSERT INTO `beaten` (`id`,`article`,`diff`,`user`) VALUES (NULL,\''.mysql_real_escape_string($change['title']).'\',\''.mysql_real_escape_string($change['url']).'\',\''.mysql_real_escape_string($currev[1]['user']).'\')');
							foreach (explode(',',$ircverbosechannel) as $y) {
								fwrite( $irc,'PRIVMSG '.$y.' :Inserting '.$change['revid'].' into `beaten` table.'."\n" ); usleep(500);
							}
						}
						else {
							//Check if user was reverted recently
							$query = 'SELECT date,user,article FROM testing WHERE user=\''.
							mysql_real_escape_string($change['user']).
							'\' AND article=\''.
							mysql_real_escape_string($change['title']).
							'\' AND date=\''.
							date( 'Y-m-d' ).
							'\';';
							echo $query;
							if (!mysql_ping($mysql)) { $mysql = 
								mysql_connect($mysqlhost,$mysqluser,$mysqlpass,/* Force reconnect --> */ true);echo "Ping?\n"; 
								mysql_select_db($mysqldb, $mysql); 
							}
							$result = mysql_query($query);
							if( !$result ) { die( "MySQL error: ".mysql_error() ); }
 
							//Remainder of $ircverbosechannel
							foreach (explode(',',$ircverbosechannel) as $y) {
								fwrite( $irc,'PRIVMSG '.$y.' :Querying if '.$change['user'].' has been reverted recently.'."\n" ); usleep(500);
							}
							echo "Querying if user has been reverted recently.\n";
							if ( mysql_num_rows( $result ) != 0 ) {
								//Remainder of $ircverbosechannel
								foreach (explode(',',$ircverbosechannel) as $y) {
									fwrite( $irc,'PRIVMSG '.$y.' :Yes, not reverting.'."\n" ); usleep(500);
								}
								continue;
							}
							//Remainder of $ircverbosechannel
							foreach (explode(',',$ircverbosechannel) as $y) {
								fwrite( $irc,'PRIVMSG '.$y.' :No, will continue to revert.'."\n" ); usleep(500);
							}
 
							//Insert into table. `reverted` is 0, will be changed to 1 later if the revert succeeded
							$query = 'INSERT INTO `testing` ' .
								'(`id`,`user`,`article`,`diff`,`old_id`,`new_id`,`reverted`,`date`,`score`) ' .								
								'VALUES ' .
								'(NULL,\''.mysql_real_escape_string($change['user']).'\',' .
								'\''.mysql_real_escape_string($change['title']).'\',' .
								'\''.mysql_real_escape_string($change['url']).'\',' .
								'\''.mysql_real_escape_string($change['old_revid']).'\',' .
								'\''.mysql_real_escape_string($change['revid']).'\',0,' .
								'\''.date( 'Y-m-d' ).'\',' .
								'\''.mysql_real_escape_string($score).'\');';
							if (!mysql_ping($mysql)) {
								$mysql = mysql_pconnect($mysqlhost,$mysqluser,$mysqlpass,/* Force reconnect --> */ true);
								if (!$mysql) { die('Could not connect: ' . mysql_error()); }
								if (!mysql_select_db($mysqldb, $mysql)) { die ('Can\'t use database : ' . mysql_error()); }
							}
							mysql_query($query);
 
							//Remainder of $ircverbosechannel
							foreach (explode(',',$ircverbosechannel) as $y) {
								fwrite( $irc,'PRIVMSG '.$y.' :Inserting '.$change['revid'].' into `testing`.'."\n" ); usleep(500);
							}
							if (mysql_affected_rows( $mysql ) == 0) {
								echo "Problem?\n";
								//Remainder of $ircverbosechannel
								foreach (explode(',',$ircverbosechannel) as $y) {
									fwrite( $irc,'PRIVMSG '.$y.' :MySQL error? '.mysql_error()."\n" ); usleep(500);
								}
								continue;
							} 
 
 							$mysqlid = mysql_insert_id();//So we can change `reverted` to 1 later
 
 							//Get rollback token
							$token = $http->get('http://en.wikipedia.org/w/api.php?action=query&prop=revisions&rvtoken=rollback&titles=Main%20Page&format=php');
							$token = unserialize($token);
							$token = $token['query']['pages'][$wpq->getpageid($change['title'])]['revisions'][0]['rollbacktoken'];
							echo "Token: $token\n";
 
							//Remainder of $ircverbosechannel
							foreach (explode(',',$ircverbosechannel) as $y) {
								fwrite( $irc,'PRIVMSG '.$y.' :Getting rollback token.'."\n" ); usleep(500);
							}
							if( $token == '' ) {
								//Remainder of $ircverbosechannel
								foreach (explode(',',$ircverbosechannel) as $y) {
									fwrite( $irc,'PRIVMSG '.$y.' :Error getting token.'."\n" ); usleep(500);
								}
							}
 
							//Do the revert!!!
							$return = $wpi->rollback(
								$change['title'],
								$change['user'],
								'Reverting possible test edit(s) by [[Special:Contributions/'.$change['user'].'|'.$change['user'].']] ' .
								'to '.(($revid == 0)?'older version':'version by '.$revdata['user']).'. ' .
								'[[User talk:'.$owner.'|Was this a mistake?]] (BOT EDIT)',
								$token,
								false
							);
 
							//Remainder of $ircverbosechannel
							foreach (explode(',',$ircverbosechannel) as $y) {
								fwrite( $irc,'PRIVMSG '.$y.' :Rolling back '.$change['revid'].'.'."\n" ); usleep(500);
							}
							echo "Reverting.\n";
 
							//If it returned true, let's warn the user
							if ($return !== false) {
								//Remainder of $ircverbosechannel
								foreach (explode(',',$ircverbosechannel) as $y) {
									fwrite( $irc,'PRIVMSG '.$y.' :Returned true, attempting to warn.'."\n" ); usleep(500);
								}
								$warning = getWarningLevel( $change['user'] );
								$warning++;//Increase warning level
 
								//Max warning is 4
								if ($warning < 5) {
									//Add shared IP notice if the user is an IP, and if it's the first warning
									$append = '';
									if ( 
										$warning == 1 &&
										preg_match('/(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/', $change['user'])//*puke* This regex just checks if it is an IP
									) {
										$append = ":''If this is a shared [[IP address]], and you didn't make the edit, consider [[Wikipedia:Why create an account?|creating an account]] for yourself so you can avoid further irrelevant notices.''\n";
									}
									//Tell the CVN bots if it is a repeat vandal/tester
									elseif ($warning > 2) {
										foreach (explode(',',$ircvandalismchannel) as $y) {
											fwrite( $irc,'PRIVMSG '.$y.' :computer bl add '.$change['user'].' x='.(24*$warning).' r=Test edits to [['.$change['title'].']] (#'.$warning.').'."\n" ); usleep(500);
										}
									}
 
									//Get talk page content
									$talk = $wpq->getpage('User talk:'.$change['user']);	
									$wpi->post(
										'User talk:'.$change['user'],$talk."\n\n".'{{subst:User:'.$user.'/warn|1='.$change['title'].'|2='.$warning.'|3='.$mysqlid.'}} '.SIGNATURE.' ~~~~~'."\n".$append,'Warning user of test edits (Warning #'.$warning.')',false,null,false); //This fugly code warns the user
									//Remainder of $ircverbosechannel
									foreach (explode(',',$ircverbosechannel) as $y) {
										fwrite( $irc,'PRIVMSG '.$y.' :Warning '.$change['user'].'.'."\n" ); usleep(500);
									}
								}
								elseif ($warning > 4) {
									//Let's report them to AIV!
									$aiv = $wpq->getpage('Wikipedia:Administrator intervention against vandalism/TB2');	
									if ( preg_match('/(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)/', $change['user']) ) {
										$template = "IPvandal";
									}
									else {
										$template = "Vandal";
									}
 
									if (!preg_match('/'.preg_quote($change['user'],'/').'/i',$aiv)) {
										//Only report if they haven't aren't already there
										foreach(explode(',',$ircaivchannel) as $y) {
											fwrite( $irc,'PRIVMSG '.$y.' :@admin Reporting [[User:'.$change['user'].']] to [[WP:AIV]]. Contributions: [[Special:Contributions/'.$change['user'].']] Block: [[Special:Blockip/'.$change['user'].']]'."\n" ); usleep(500);
										}
										foreach (explode(',',$ircvandalismchannel) as $y) {
											fwrite( $irc,'PRIVMSG '.$y.' :computer bl add '.$change['user'].' x='.(24*$warning).' r=Vandalism to [['.$change['title'].']] (#'.$warning.").\n" ); usleep(500);
										}
										$aiv = $aiv . "\n" .
										"* {{".$template."|1=".$change['user']."}} ".
										"User made possible test edits, such as [http://en.wikipedia.org/wiki/?diff=".$change['revid']." 1]. ".SIGNATURE." ~~~~~";
										$wpi->post('Wikipedia:Administrator intervention against vandalism/TB2',$aiv,'Reporting [[Special:Contributions/'.$change['user']."|".$change['user']."]] (BOT EDIT)");
										foreach (explode(',',$ircverbosechannel) as $y) {
											fwrite( $irc,'PRIVMSG '.$y.' :Warning level is 4, reporting to AIV.'."\n" ); usleep(500);
										} 
									} else {
										foreach (explode(',',$ircaivchannel) as $y) {
											fwrite( $irc,'PRIVMSG '.$y.' :@admin [[User:'.$change['user'].']] has vandalized at least one time while being listed on [[WP:AIV]].  Contributions: [[Special:Contributions/'.$change['user'].']] Block: [[Special:Blockip/'.$change['user'].']]'."\n" ); usleep(500); //Ping the admins!
										}
									}
								}
 
								//Now that it was reverted, let's update the table
								mysql_query('UPDATE `testing` SET `reverted` = 1 WHERE `id` = \''.mysql_real_escape_string($mysqlid).'\'');
							} 
							else {//Somehow, it returned false. Let's figure out why.
								//Remainder of $ircverbosechannel
								foreach (explode(',',$ircverbosechannel) as $y) {
									fwrite( $irc,'PRIVMSG '.$y.' :Returned false, figuring out why.'."\n" ); usleep(500);
								}
								$currev = $wpapi->revisions($change['title']);
								if (($currev[0]['revid'] != $change['revid']) && ($currev[0]['user'] != $change['user'])) {
									//We've been beaten!!!
									mysql_query('INSERT INTO `beaten` (`id`,`article`,`diff`,`user`) VALUES (NULL,\''.mysql_real_escape_string($change['title']).'\',\''.mysql_real_escape_string($change['url']).'\',\''.mysql_real_escape_string($rev['user']).'\')');
									//Remainder of $ircverbosechannel
									foreach (explode(',',$ircverbosechannel) as $y) {
										fwrite( $irc,'PRIVMSG '.$y.' :I was beaten by '.$rev['user'].'!'."\n" ); usleep(500);
									}
								}
								else {
									//Remainder of $ircverbosechannel
									foreach (explode(',',$ircverbosechannel) as $y) {
										fwrite( $irc,'PRIVMSG '.$y.' :There was an unknown rollback error.'."\n" ); usleep(500);
									}
									file_put_contents('/home/soxred93/bots/soxbot-test/errors.txt',file_get_contents('/home/soxred93/bots/soxbot-test/errors.txt')."Date: ".date( 'Y-m-d' )."\n".$return."\n----------------------\n") or die('Error');
									foreach (explode(',',$ircverbosechannel) as $y) {
										fwrite( $irc,'PRIVMSG '.$y.' :Rollback result has been posted to errors.txt.'."\n" ); usleep(500);
									}
									echo "ERROR\n";
								}
							}//End if ($return !== FALSE)
						}//End if beaten check
						//Remainder of $ircverbosechannel
						foreach (explode(',',$ircverbosechannel) as $y) {
							fwrite( $irc,'PRIVMSG '.$y.' :-- Done --.'."\n" ); usleep(500);
						}
					}//End check for vandalism
					//unset($pids[$feedpid]);
					//unset($pids[posix_getpid()]);
					die();
				}//End regex for parsing IRC feed
				//unset($pids[$feedpid]);
			}//End checking for privmsg
		}//End while
	}//End while
?>
