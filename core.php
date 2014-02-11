<?php
include 'addons/template.php';
class irc {
	private $session = array(
		'server'=>'irc.eu.gamesurge.net',
		'port'=>6667,
		'channels'=>array('moh-irc-test-hue', 'astral'),
		'nick'=>'Zohan',
		'auth'=>array('moh','65651350'),
		'pass'=>null,
		'timeout'=>3,
		'socket'=>null,
		'reconnect'=>3,
		'idle'=>1,
		'enabled'=>1,
	);
	private $OPaccess = array(
		'moh.user.gamesurge',
	);
	private $addons;
	private $error;
	public $buffer;

	public function __construct($conf=null) {
		if(isset($conf)) {
			self::Log('Starting');
			self::SetUp($conf);
		} else {
			self::Log(' =========== Starting with default settings ============== ');
		}
		self::LoadAddOns();
	}
	public function GetError() {
		return $this->error;
	}
	public function Error($msg) {
		$this->error = $msg;
	}
	public function SetUp($conf=array()) {
		foreach($conf as  $Key=>$v) {
			$this->session[$Key] = $v;
		}
	}
	public function Log($data, $replace=null) {
		if(isset($replace)) {
				$format = '[' . date("H:i:s") . '] ' . $data;
		} else {
			$format = '[' . date("H:i:s") . '] ' . $data . "\n";
		}
		$LogFile = 'connection.log';
		echo $format;
		file_put_contents($LogFile, $format, FILE_APPEND | LOCK_EX);
	}
	public function LoadAddOns() {
		$this->addons[0] = new template($this);
	}
	public function AddonHandler($data) {
		$command = $data[0];
		$messages = $data[1];
		$channel = (isset($data[2]) ? $data[2] : null);
		if ( $command == 'SEND' ) {
			foreach($messages as $message) {
				self::Send($message, $channel);
				usleep(100000);
			}
		}
		if ( $command == 'NOTICE' ) {
			foreach($messages as $message) {
				self::Notice($message, $channel);
				usleep(100000);
			}
		}
	}
	public function Connect($Init=0) {
		self::Log('[Connection]: Initializing');
		$this->session['socket'] = @fsockopen($this->session['server'], $this->session['port'], $errno, $errstr, $this->session['timeout']);
		self::Register();
		
	}
	public function Read($bytes=128) {
		$this->Buffer = fgets($this->session['socket'], $bytes);
		if(!empty($this->Buffer)) {
			if( self::Alive() && strpos($this->Buffer, '') === false) {
			
				while ( strpos($this->Buffer, "\n") === false ) {
					$this->Buffer .= fgets($this->session['socket'], $bytes);
				}
				
				if($this->session['enabled'] == 1) { // ignore addons if disabled
					foreach($this->addons as $key=>$value) {
						if( $returns = $this->addons[$key]->__Handler($this->Buffer)) {
							self::AddonHandler($returns);
							$HandleByAddon=true;
						}
					}
				}
				
				if(isset($HandleByAddon)) {
				
				} elseif ( self::Pong() ) {
				
				} elseif ( self::ChatLine() ) {
				
				} else {
					self::Log('[SERVER] ' . $this->Buffer, 1);
				}
			}
		}
	}
	public function ChatLine() {
		$exp=explode(' ', $this->Buffer, 4);
		if(!isset($exp[1])) { return false; }
		if($exp[1] == 'PRIVMSG') {
			$args=array();
			$sender=explode('!', $exp[0], 2);
			$args['message'] = substr($exp[3], 1);
			$args['sender'] = substr($sender[0], 1);
			if(substr($exp[2], 0, 1) != '#') {
				$args['pm'] = 1;
			} else {
				$args['channel'] = $exp[2];
			}
			if(isset($args['pm']) && self::OwnerCommand($exp)) {
			
			} else {
				self::Log((isset($args['pm']) ? '[PM] [' . $args['sender'] . ']: ' . $args['message'] : '[' . $args['channel'] . '] [' . $args['sender'] . ']: ' . $args['message']), 1);
			}
			return true;
		} elseif ( $exp[1] == 'KICK') {
			$exp=explode(' ', $this->Buffer, 5);
			$sender=explode('!', $exp[0], 2);
			$byWho = substr($sender[0], 1);
			$msg = str_replace(array("\n", "\r"), '', substr($exp[4], 1));
			if ( $exp[3] == $this->session['nick'] ) {
				self::Log('[Status]: Got kicked from ' . $exp[2] . ' by ' . $byWho . ($msg != $byWho ? ' (Reason: ' . $msg . ')' : ''));
				self::Join();
				self::Send('NO U', $exp[2]);
			} 
			return true;
		}
		return false;
	}
	public function OwnerCommand($data) {
		$userhost = explode('@', $data[0], 2);
		if(in_array($userhost[1], $this->OPaccess)) {
			$msg = substr($data[3], 1);
			$msg = str_replace(array("\n", "\r"), '', $msg);
			if ( $msg == '@disable' ) {
				$this->session['enabled'] = 0;
				self::Log('[MASTER$CMD]: DISABLE');
			} elseif ( $msg == '@enable' ) {
				$this->session['enabled'] = 1;
				self::Log('[MASTER$CMD]: ENABLE');
			} elseif ( $msg == '@notice' ) {
				self::Notice('pokes you', 'moh');
			} else {
				self::Log('[MASTER]: ' . $msg);
			}
			return true;
		}
	}
	public function Write($data, $nn=null, $nolog=null) {
		//if(self::Alive()) {
			if(!isset($nolog)) {
				self::Log('[CLIENT] ' . $data, (isset($nn) ? 1 : null));
			}
			fputs($this->session['socket'], $data."\n");
		//}
	}
	public function Send($message, $channel=null /* sends to all if not set */) {
		if(isset($channel)) {
			$format = 'PRIVMSG ' . $channel . ' :' . $message;
			self::Log('[SEND][' . $channel . ']: ' . $message);
			self::Write($format, 1, 1);
		} else {
			self::Log('[SEND][ALL]: ' . $message);
			foreach($this->session['channels'] as $channel) {
				$format = 'PRIVMSG #' . $channel . ' :' . $message;
				self::Write($format, 1, 1);
			}
		}
	}
	public function Notice($message=false, $user=false) {
		if ( !$message || !$user ) { return false; } /** the fuck are you doing **/
		$format = 'NOTICE %s :%s';
		self::Log('[NOTICE][' . $user . ']: ' . $message);
		self::Write(sprintf($format, $user, $message), 1, 1);
		return true;
	}
	public function Register() {
		self::Write('PASS NOPASS');
		self::Write('NICK '.$this->session['nick']);
		self::Write('USER '.$this->session['nick'].' USING PHP IRC');
		while (1) {
			self::Read();
			if(!empty($this->Buffer)) {
				if(strpos($this->Buffer, ' 433 * '.$this->session['nick'])) {
					$this->session['nick'] .= rand(0,9);
					self::Write('NICK '.$this->session['nick']);
				}
				if(strpos($this->Buffer, ' 001 '.$this->session['nick'])) {
					self::Log('[Status]: Ready');
					break;
				}
			}
		}
		self::Auth();
		self::Join();
	}
	public function Auth() {
		if(isset($this->session['auth'])) {
			self::Write('AUTHSERV auth ' . $this->session['auth'][0] . ' ' . $this->session['auth'][1]);
			self::Write('MODE ' . $this->session['nick'] . ' +x');
		}
	}
	public function Pong() {
		if(substr($this->Buffer, 0, 6) == 'PING :') {
			// self::Log('[PING]');
			// self::Log('[PONG]');
			self::Write('PONG :'.substr($this->Buffer, 6), 1, 1);
			return true;
		}
		return false;
	}
	public function Alive() {
		if(feof($this->session['socket'])) {
			self::Log('[Connection]: Disconnected');
			$this->session['idle']=0;
			return false;
		}
		return true;
	}
	public function Join() {
		foreach($this->session['channels'] as $channel) {
			self::Write("JOIN #" . $channel);
			while(1) {
				self::Read();
				if(strpos($this->Buffer, ' 366 '.$this->session['nick'])) {
					self::Log('[JOINED]: #' . $channel);
					break;
					return true;
				}
			}
		}
	}
	public function stayIdle() {
		self::Log('[Status]: Idle');
		while($this->session['idle']) {
			self::Read();
		}
	}
	
}

$irc = new irc;
$irc->Connect();
$irc->stayIdle();
?>