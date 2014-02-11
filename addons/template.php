<?php
/** check AddonHandler method for more info **/
class template {
	public function __constcurt($data=null) {
		return $this->__Handler($data);
	}
	
	public function ItemColor($int) {
		$colors = array(14, null, 03, 12, 06, 07, 08);
		return $colors[$int];
	}
	public function __Handler($data=null) {
		$buffer = $data;
		$exp=explode(' ', $buffer, 4);
		if(!isset($exp[1])) { return false; }
		if($exp[1] == 'PRIVMSG') {
			$args=array();
			$sender=explode('!', $exp[0], 2);
			$args['message'] = str_replace(array("\n", "\r"), '', substr($exp[3], 1));
			$args['sender'] = substr($sender[0], 1);
			if(substr($exp[2], 0, 1) != '#') {
				$args['pm'] = 1;
			} else {
				$args['channel'] = $exp[2];
			}
			if ( $args['message'] == '!status' ) {
				$check = array(
					array('game server (Emberflame 108.61.69.235:8085)', '108.61.69.235', 8085),
					array('login server 1 (login.astralwow.info:3724)', 'login.astralwow.info', 3724),
					array('login server 2 (login2.astralwow.info:3724)', 'login2.astralwow.info', 3724),
					array('login server 1 (91.205.173.171:3724)', '91.205.173.171', 3724),
					array('login server 2 (108.61.69.235:3724)', '108.61.69.235', 3724),
				);
				function status($ip, $port=false, $timeout=1) {
					$socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
					if ( $socket ) {
						fclose($socket);
						return true;
						} else {
						return false;
					}
					return false;
				}
				for ( $i = 0, $c = count($check); $i < $c; ++$i ) {
					$serverstatus[] = (status($check[$i][1], (isset($check[$i][2]) ? $check[$i][2] : false)) ? 'online - ' . $check[$i][0] : 'DOWN - ' . $check[$i][0]);
				}
				return array('NOTICE', array(implode(' | ', $serverstatus)), $args['sender']);
			}
			$pos = strpos($args['message'], 'http');
			if ($pos !== false) {
				$wowhead = strpos($args['message'], 'www.wowhead.com');
				if( $wowhead === false ) {
					$pattern = '(http[s]?://.[^\s]+)';
					preg_match($pattern, $args['message'], $link);
					if(isset($link[0])) {
						$headers = get_headers($link[0], 1);
						if ( isset($headers['Content-Type']) ) {
							if ( strpos($headers['Content-Type'],'text/html') === false ) { return false; }
							$data = file_get_contents($link[0]);
							if ($data) { 
								$title = explode('<title>', $data);
								$title = explode('</title>', $title[1]);
								$title = preg_replace('/(\s)+/', ' ', str_replace(array("\n", "\r", '&#39;'), array('', '', '\''), $title[0]));
								return array('SEND',array('' . $title . ''), $args['channel']);
							}
						}
					}
				} else {
					$item = '(http://.*item=[0-9]+)';
					preg_match($item, $args['message'], $matches);
					if(isset($matches[0])) {
						$xmldata = file_get_contents($matches[0]. '?xml');
						$pattern = '(<name>.*\[(.[^\]]*)\].*<\/name>.*<quality id="(?P<quality>[0-9]+)">.*<inventorySlot id="[0-9]+">(.*)<\/inventorySlot>)';
						preg_match($pattern, $xmldata, $found);
						$html = explode('<htmlTooltip><![CDATA[', $xmldata);
						$html = explode(']]></htmlTooltip>', $html[1]);
						$html=$html[0];
						$out = preg_replace('(\<(/?[^\>]+)\>)', "\n", $html);
						$out = str_replace(array('&quot;', '&nbsp;'), array("\"", ' '), $out);
						$e=explode("\n", $out);
						foreach($e as $key=>$val) {
							if($val == 'Sell Price: ') break;
							if($val == '' or $val == ' ') continue;
									if($key == 4) {
										if($found['quality'] != 1 ) {
											/** bye bye colors :( $out_arr[] = "" . $this->ItemColor($found['quality']) . "" . $val . "99";*/
											$out_arr[] = "" . $val . "";
										} else {
											$out_arr[] = "" . $val . "";
										}
									} else {
										$out_arr[]=$val;
									}
						}
						//print_r($out_arr);
						return array('SEND', array(implode(' | ', $out_arr)), $args['channel']);
					}
				}
			} 
		}
	}
	
}
?>