<?php
	class ConnectSSH {
		public $ip_host;
		private $username;
		private $password;
		public $connect;
		private $stream;
		private $errors = array();
		private $local_path = "/var/www/html/NetworkAdmin/php/";
		private $remote_path;
		private $filename;

		public $db_connect;
		public $db_prefix;

		function __construct($ip_host, $username, $password){
			if (!function_exists("ssh2_connect")) {
        		array_push($this->errors, "La función ssh2_connect no existe");
			}

        	if(!($this->connect = @ssh2_connect($ip_host, 22))){
				$this->ip_host = $ip_host;
        		array_push($this->errors, "No hay conexión con al dirección IP: " . $ip_host);
		    } else {
		        if(!ssh2_auth_password($this->connect, $username, $password)) {
        			array_push($this->errors, "Autenticación invalida");
		        } else {
					$this->ip_host 		= $ip_host;
					$this->username 	= $username;
					$this->password 	= $password;
					$this->remote_path 	= "/home/";
		        }
		    }
		}

		public function FinalConnect($ip_host, $username, $password){
			if (!function_exists("ssh2_connect")) {
        		array_push($this->errors, "La función ssh2_connect no existe");
			}

        	if(!($this->connect = ssh2_connect($ip_host, 22))){
				$this->ip_host = $ip_host;
        		array_push($this->errors, "No hay conexión con al dirección IP: " . $ip_host);
		    } else {
		        if(!ssh2_auth_password($this->connect, $username, $password)) {
        			array_push($this->errors, "Autenticación invalida");
		        } else {
					$this->ip_host 		= $ip_host;
					$this->username 	= $username;
					$this->password 	= $password;
					$this->remote_path 	= "/home/".$username."/";
		        }
		    }

		    return true;
		}

		public function RunLines($RL){
			if(!($this->stream = ssh2_exec($this->connect, $RL)))
		        return "Falló: El comando no se ha podido ejecutar.";
			stream_set_blocking($this->stream, true);
            while ($buf = fread($this->stream, 4096))
                $data .= $buf;
            
            if (fclose($this->stream))
            	return $data;
		}

		public function writeFile($Instructions, $filename){
			$inputfile = file_put_contents($this->local_path.$filename, implode("\n", $Instructions));
			if ($inputfile === false)
				die("El script <b>".$filename."</b>, no se ha podido crear.");
			@chmod($this->local_path.$filename, 0777);
		
			return true;
		}

		public function sendFile($filename){
			$scp = ssh2_scp_send($this->connect, $this->local_path.$filename, $this->remote_path.$filename, 0777);
			if (!$scp){
				return false;
			} else {
				return true;
			}
		}

		public function recvFile($remotePath){
			$scp = ssh2_scp_recv($this->connect, $remotePath, "/Backups");
			if (!$scp){
				return false;
			} else {
				return true;
			}
		}

		public function deleteFile($filename){
			if (!unlink($this->local_path.$filename))
				return false;
			return true;
		}

		public function getDHCPShowAssignIP(){
			$filename = "getDHCPShowAssignIP.sh";
			$ActionArray[] = 'echo "="';
			array_push($ActionArray, "MES=$(service isc-dhcp-server status | tail -n10 | grep 'DHCPACK' | awk {'print $1'})");
			array_push($ActionArray, "DIA=$(service isc-dhcp-server status | tail -n10 | grep 'DHCPACK' | awk {'print $2'})");
			array_push($ActionArray, "HORA=$(service isc-dhcp-server status | tail -n10 | grep 'DHCPACK' | awk {'print $3'})");
			array_push($ActionArray, "IP=$(service isc-dhcp-server status | tail -n10 | grep 'DHCPACK' | awk {'print $8'})");
			array_push($ActionArray, "MAC=$(service isc-dhcp-server status | tail -n10 | grep 'DHCPACK' | awk {'print $10'})");
			array_push($ActionArray, "INTERFAZ=$(service isc-dhcp-server status | tail -n10 | grep 'DHCPACK' | awk {'print \$NF'})");
			array_push($ActionArray, 'echo "${MES[*]} | "');
			array_push($ActionArray, 'echo "${DIA[*]} | "');
			array_push($ActionArray, 'echo "${HORA[*]} | "');
			array_push($ActionArray, 'echo "${IP[*]} | "');
			array_push($ActionArray, 'echo "${MAC[*]} | "');
			array_push($ActionArray, 'echo "${INTERFAZ[*]} | "');
			array_push($ActionArray, 'echo "="');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getDNSFileZones(){
			$filename = "getDNSFileZones.sh";
			$ActionArray[] = "ZONAS=($(cat /etc/bind/named.conf.local | grep 'file' | awk {'print $2'} | tr -d '\";'))";
			array_push($ActionArray, 'CANT_ZONAS=${#ZONAS[*]}');
			array_push($ActionArray, 'for (( i = 0; i < $CANT_ZONAS; i++ )); do');
			array_push($ActionArray, '	DOMINIO=$(cat ${ZONAS[$i]} | grep "SOA" | awk {"print $4"} | sed "s/.$//g")');
			array_push($ActionArray, '	TRADUC=$(cat ${ZONAS[$i]} | grep -e "IN" | tail -n1 | awk "! /$DOMINIO/ {print $1}")');
			array_push($ActionArray, '	IP=$(cat ${ZONAS[$i]} | grep "IN" | tail -n1 | awk "! /$DOMINIO/ {print $4}")');
			array_push($ActionArray, '	echo " ${ZONAS[$i]},$DOMINIO,${TRADUC[*]}.$DOMINIO,${IP[*]}"');
			array_push($ActionArray, "done");
			array_push($ActionArray, 'echo "="');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getNetworkIPLocal(){
			$IP 		= shell_exec('ip route show | awk {"print $NF"}');
			$ArrayIP 	= explode("metric ", $IP);
			$ArrayFinal = array();
			for ($i=0; $i < count($ArrayIP); $i++){
				$ArrayIPTwo = explode(" dev ", $ArrayIP[$i]); 
				for ($j=0; $j < count($ArrayIPTwo); $j++)
					if (strpos($ArrayIPTwo[$j], 'static') != true && strpos($ArrayIPTwo[$j], 'link src') != true && strpos($ArrayIPTwo[$j], 'via') != true)	
					  	array_push($ArrayFinal, trim(substr($ArrayIPTwo[$j], 4)));
			}
			return $ArrayFinal;
		}

		public function getNmapTrackingIP($RangeIPAddress){
			if (is_array($RangeIPAddress)){
				for ($i = 0; $i < count($RangeIPAddress); $i++){
					$val = shell_exec("nmap -sP ".$RangeIPAddress[$i]);
					$ArrayContent 	= explode("Host is up", $val); 
					$ArrayData 		= array();
					for ($i=0; $i < count($ArrayContent); $i++) { 
						$ArrayContentTwo = explode("Nmap scan report for ", $ArrayContent[$i]); 
						for ($j=0; $j < count($ArrayContentTwo); $j++) 
							if (filter_var(trim($ArrayContentTwo[$j]), FILTER_VALIDATE_IP))
							    array_push($ArrayData, $ArrayContentTwo[$j]);
							
					}
				}
				return $ArrayData; 
			} else if (is_string($RangeIPAddress)) {
				$val = shell_exec("nmap -sP ".$RangeIPAddress);
				$ArrayContent 	= explode("Host is up", $val); 
				$ArrayData 		= array();
				for ($i=0; $i < count($ArrayContent); $i++) { 
					$ArrayContentTwo = explode("Nmap scan report for ", $ArrayContent[$i]); 
					for ($j=0; $j < count($ArrayContentTwo); $j++)
						if (filter_var(trim($ArrayContentTwo[$j]), FILTER_VALIDATE_IP))
						    array_push($ArrayData, $ArrayContentTwo[$j]);
						
				}
				return $ArrayData; 
			}
		}
		
		public function getErrors(){
			return implode("<br/>", $this->errors);
		}

		public function testing(){
			return "Okay";
		}

		public function getIPLocalCurrent(){
			return shell_exec("ip route show default | awk '/default/ {print $3}'");
		}

		public function getMyIPServer(){
			return shell_exec("ip -4 route get 1.1.1.1 | awk {'print $7'} | tr -d '\n'");
		}

		public function checkNetwork($ip_net){
			if ($this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."network WHERE ip_net='".trim($ip_net)."';")->num_rows > 0)
				return true;

			return false;
		}

		public function checkHost($ip_host){
			if ($this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE ip_host='".trim($ip_host)."';")->num_rows > 0)
				return true;

			return false;
		}

		public function addNetwork($ip_net, $checked = "0", $alias = ""){
			if ($this->db_connect->query("INSERT INTO ".$this->db_prefix."network (ip_net, checked, alias) VALUES ('".trim($ip_net)."','".$checked."', '".$alias."');"))
				return true;

			return false;
		}

		public function updateNetwork($ip_net, $checked){
			if ($this->db_connect->query("UPDATE ".$this->db_prefix."network SET checked='".$checked."' WHERE ip_net='".$ip_net."';"))
				return true;

			return false;
		}

		public function updateNetworkNextRouterAlias($ip_net_next, $alias){
			if ($this->db_connect->query("UPDATE ".$this->db_prefix."host SET alias='".$alias."' WHERE net_next='".$ip_net_next."';"))
				return true;

			return false;
		}

		public function updateNetworkAlias($ip_net, $alias){
			if ($this->db_connect->query("UPDATE ".$this->db_prefix."network SET alias='".$alias."' WHERE ip_net='".$ip_net."';"))
				return true;

			return false;
		}

		public function updateHostAlias($ip_host, $alias){
			if ($this->db_connect->query("UPDATE ".$this->db_prefix."host SET alias='".$alias."' WHERE ip_host='".$ip_host."';"))
				return true;

			return false;
		}

		public function updateHostRouterAlias($ip_net, $alias){
			if ($this->db_connect->query("UPDATE ".$this->db_prefix."host SET alias='".$alias."' WHERE ip_net='".$ip_net."' AND router='1';"))
				return true;

			return false;
		}

		public function addHost($ip_net, $ip_host, $router, $net_next, $alias = ""){
			$query = "INSERT INTO ".$this->db_prefix."host (ip_net, ip_host, router, net_next, alias) VALUES ('".$ip_net."', '".$ip_host."', '".$router."', '".$net_next."', '".$alias."');";
			
			if ($this->db_connect->query($query))
				return true;

			return false;
		}

		public function getIPNetFromIPHost($ip_host){
			return $this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE ip_host='".$ip_host."' LIMIT 1;");
		}

		public function getHostTypeRouterLast(){
			return $this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE router='1' AND net_next!='-' ORDER BY ip_net DESC LIMIT 1;");
		}

		public function getHostNetwork($network){
			return $this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE ip_net='".$network."';");
		}

		public function getHostTypeRouter(){
			return $this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE router='1';");
		}

		public function getHostTypeSwitch($IPNet){
			return $this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE ip_net='".$IPNet."' AND router='0';");
		}

		public function getHostTypeHost(){
			return $this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE router='0';");
		}

		public function getAllHost(){
			return @$this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host;");
		}

		public function getHostWithOutInterfaces(){
			return @$this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."host WHERE NOT (router='1' AND net_next='-');");
		}

		//Extrae todas las direcciones de red.
		public function getIPNet(){
			return @$this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."network;");
		}

		public function getIPNetNext($ip_net){
			return $this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."network WHERE ip_net>'".$ip_net."' ORDER BY ip_net DESC LIMIT 1;")->fetch_array(MYSQLI_ASSOC)['ip_net'];
		}

		public function getIPNetLast(){
			return @$this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."network ORDER BY ip_net DESC LIMIT 1;");
		}

		public function getIPNetOnly(){
			return @$this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."network LIMIT 1;")->fetch_array(MYSQLI_ASSOC)['ip_net'];
		}

		public $CommandIpRoute = "ip route | sed -e '/src/ !d' | sed '/default/ d' | cut -d ' ' -f1";

		//Limpieza de tablas
		public function InitTables(){
			$this->db_connect->query("TRUNCATE ".$this->db_prefix."network;");
			$this->db_connect->query("TRUNCATE ".$this->db_prefix."host;");
		}

		public function IsRouter($IPHost, $user = "network", $pass = "123"){
			$this->FinalConnect($IPHost, $user, $pass);

			$RL[] = "cat /proc/sys/net/ipv4/ip_forward";
			
			//Se obtiene valores booleanos (0, 1 = enrutador)
			$ip_forward = (int)trim($this->RunLines(implode("\n", $RL)));

			return $ip_forward;
		}

		public function getIpRouteLocal(){
			return trim(explode("\n", trim(shell_exec($this->CommandIpRoute)))[0]);
		}

		public function getIpRouteRemote($IPHost, $user = "network", $pass = "123"){
			$this->FinalConnect($IPHost, $user, $pass);

			$RA[] = $this->CommandIpRoute;

			return implode("\n", explode("\n", $this->RunLines(implode("\n", $RA))));
		}

		public function getCountNetwork(){
			return @(int)$this->db_connect->query("SELECT DISTINCT count(*) AS 'count' FROM ".$this->db_prefix."network;")->fetch_array()['count'];
		}

		public function getCountNetworkChecked(){
			return @(int)$this->db_connect->query("SELECT DISTINCT count(*) AS 'count' FROM ".$this->db_prefix."network WHERE checked='0';")->fetch_array()['count'];
		}

		public function getAllNetworkChecked(){
			return @$this->db_connect->query("SELECT DISTINCT * FROM ".$this->db_prefix."network WHERE checked='0' LIMIT 1;");
		}

		#Rastreo de Red
		public function SpaceTest(){
			$this->InitTables();

			do {
				if (@!$this->getCountNetwork())
					@$this->addNetwork($this->getIpRouteLocal());

				if ($this->getAllNetworkChecked()->num_rows > 0){
					$Network = $this->getAllNetworkChecked()->fetch_array(MYSQLI_ASSOC)['ip_net'];
					$D = $this->SondearRed($Network);
					unset($D[count($D) - 1]);

					foreach ($D as $value) {
						$ip_forward = @$this->IsRouter($value);
						$ArrayNets = @explode("\n", $this->getIpRouteRemote($value));
						
						$NextNet = $ArrayNets[0];
						$NextNet = "-";

						if ($ip_forward){
							$NextNet = $ArrayNets[1];
							if (trim($Network) == trim($NextNet)){
								$NextNet = "-";
							} else {
								$this->addNetwork($NextNet);
							}
						}

						$this->addHost($Network, $value, $ip_forward, $NextNet);
		    		}

					$this->updateNetwork($Network, 1);					
				}
			} while ($this->getCountNetworkChecked());
		}

		public function IPRouteShow($IPHost){
			$R = $this->getIPNet();

			if ($IPHost == "localhost"){
				$AddrIPNext = explode("\n", $this->getIPLocal());

				return trim($AddrIPNext[0]);
			} else {
				$this->FinalConnect($IPHost, "network", "123");
				$RA[] = "ip route | sed '/default/ d' | cut -d ' ' -f1";

				$AddrIPNext = explode("\n", $this->RunLines(implode("\n", $RA)));
			}

			if ($R->num_rows > 0){
				while ($row = $R->fetch_array(MYSQLI_ASSOC)){
					if (in_array($row['ip_net'], $AddrIPNext)){
						return $row['ip_net'];
					}
				}
			}
		}

		public function SondearRed($IPNet){
			return explode("\n", shell_exec("nmap ".$IPNet." -n -sP | grep report | awk '{print $5}'"));
		}

		public function RastreoTotal($IPNet){
			return explode("\n", shell_exec("nmap ".$IPNet." -n -sP"));
		}

		public function SrMartinez(){
			return explode("\n", shell_exec("nmap 192.168.100.0/24 -n -sP | grep report | awk '{print $5}'"));
		}

		public function getMemoryState(){
			$filename = "getMemoryState.sh";
			$ActionArray[] = "MEMORIA=($(free -m | grep 'Mem' | cut -d ':' -f2))";
			array_push($ActionArray, 'echo "${MEMORIA[0]},${MEMORIA[1]},${MEMORIA[2]},"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getSwapState(){
			$filename = "getSwapState.sh";
			$ActionArray[] = "SWAP=($(free -m | egrep '(Intercambio|Swap)' | cut -d ':' -f2))";
			array_push($ActionArray, 'echo "${SWAP[0]},${SWAP[1]},${SWAP[2]},"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getCpuState(){
			$filename = "getcpuState.sh";
			$ActionArray[] = "NameModel=($(cat /proc/cpuinfo | grep name | cut -d ':' -f2))";
			array_push($ActionArray, "Velocidad=$(cat /proc/cpuinfo | grep name | cut -d ' ' -f 10)");
			array_push($ActionArray, "UsoUser=$(top -n1 -b | grep '%Cpu' | awk {'print $2'} | sed 's/,/./g')");
			array_push($ActionArray, "UsoSystem=$(top -n1 -b | grep '%Cpu' | awk {'print $4'} | sed 's/,/./g')");
			// array_push($ActionArray, 'UsoTotal=$(echo "$UsoUser + $UsoSystem" | bc)');
			// array_push($ActionArray, 'Disponible=$(echo "100 - $UsoTotal" | bc)');
			array_push($ActionArray, "TotalProc=$(ps ax | wc -l)");
			array_push($ActionArray, 'echo "${NameModel[*]},$UsoUser,$UsoSystem,$TotalProc,"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getDiskState(){
			$filename = "getDiskState.sh";
			$ActionArray[] = 'Disk=($(df -H /dev/sda1 | sed "1d" | sed "s/,/./g" | tr -d "G"))';
			array_push($ActionArray, 'echo "${Disk[1]},${Disk[2]},${Disk[3]},"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getProcState(){
			$filename = "getProcState.sh";
			$ActionArray[] = "Proc=($(ps axo pid,pcpu,size,time,cmd --sort -pcpu | sed '1d' | awk {'print $1 ,$2 ,$3 ,$4 ,$5'}))";
			array_push($ActionArray, 'echo "${Proc[*]},"');	
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getNetAddress(){
			$filename = "getNetAddress.sh";
			$ActionArray[] = 'Interfaces=($(ip addr show | egrep "[1-9]: " | cut -d ":" -f2 | tr -d " "))';
			array_push($ActionArray, 'for i in ${Interfaces[*]}; do');
			array_push($ActionArray, 'DirIP=$(ip addr show $i | grep -w inet | cut -d " " -f6 | cut -d "/" -f1)');
			array_push($ActionArray, 'if [[ $DirIP != "" ]]; then');
			array_push($ActionArray, 'echo "$i|$DirIP,"');
			array_push($ActionArray, 'else');
			array_push($ActionArray, 'echo "$i|No tiene IP asignada,"');
			array_push($ActionArray, 'fi');
			array_push($ActionArray, 'done');	
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getTableRoute(){
			$filename = "getTableRoute.sh";
			$ActionArray[] = "Net=$(ip route show | awk {'print $1'})";
			array_push($ActionArray, 'for i in ${Net[*]}; do');
			array_push($ActionArray, '	Comp=$(ip route show | grep -w "$i" | grep -w via)');
			array_push($ActionArray, '	if [[ $Comp != "" ]]; then');
			array_push($ActionArray, '		Int=$(ip route show | grep -w "$i" | cut -d " " -f5)');
			array_push($ActionArray, '		Salt=$(ip route show | grep -w "$i" | cut -d " " -f3)');
			array_push($ActionArray, '		echo "$i|$Int|$Salt,"');
			array_push($ActionArray, '	else');
			array_push($ActionArray, '		Int=$(ip route show | grep -w "$i" | cut -d " " -f3)');
			array_push($ActionArray, '		echo "$i|$Int|-,"');
			array_push($ActionArray, '	fi');
			array_push($ActionArray, 'done');	
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getPortsListen(){
			$filename = "getPortsListen.sh";
			$ActionArray[] = "Ports=($(lsof -i -nP | sed '1d' | egrep -v '(ESTAB|WAIT)' | awk {'print $9 ,$8 ,$5 ,$1'} | cut -d':' -f2 | uniq))";
			array_push($ActionArray, 'echo "${Ports[*]},"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $ActionArray));
			return getErrors();
		}	

		public function getBatteryState(){
			$filename = "getBatteryState.sh";
			$ActionArray[] = "Porcentaje=$(upower -i /org/freedesktop/UPower/devices/battery_BAT0 | grep percentage | awk {'print $2'} | tr -d '%')";
			array_push($ActionArray, "StatusBat=$(upower -i /org/freedesktop/UPower/devices/battery_BAT0 | grep state | awk {'print $2'})");
			array_push($ActionArray, 'echo "$Porcentaje,$StatusBat,"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getInfoOS(){
			$filename = "getInfoOS.sh";
			$ActionArray[] = "HostName=$(hostname)";
			array_push($ActionArray, "NameOs=$(lsb_release -si)");
			array_push($ActionArray, "Version=$(lsb_release -sr)");
			array_push($ActionArray, "TypeMachine=$(uname -m)");
			array_push($ActionArray, "Kernel=$(uname -r)");
			array_push($ActionArray, 'echo "$HostName,$NameOs,$Version,$TypeMachine,$Kernel,"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getUsersConnected(){
			$filename = "getUsersConnected.sh";
			$ActionArray[] = "Users=($(w | sed '1,2d' | awk {'print $1 ,$4'}))";
			array_push($ActionArray, 'echo "${Users[*]},"');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getNetworkServices() {
			$filename = "getNetworkServices.sh";
			$ActionArray[] = "Services=$(lsof -i -n | egrep -v '(ESTAB|WAIT)' | sed '1d' | awk {'print $1'} | uniq)";
			array_push($ActionArray, 'echo "${Users[*]},"');
			array_push($ActionArray, 'for i in ${Services[*]}; do');
			array_push($ActionArray, 'case $i in');
			array_push($ActionArray, '"sshd" )');
			array_push($ActionArray, 'echo "SSH,"');
			array_push($ActionArray, ';;');
			array_push($ActionArray, '"apache2" )');
			array_push($ActionArray, 'echo "HTTP,"');
			array_push($ActionArray, ';;');
			array_push($ActionArray, '"mysqld" )');
			array_push($ActionArray, 'echo "MySQL,"');
			array_push($ActionArray, ';;');
			array_push($ActionArray, '"named" )');
			array_push($ActionArray, 'echo "DNS,"');
			array_push($ActionArray, ';;');
			array_push($ActionArray, '"vsftpd" )');
			array_push($ActionArray, 'echo "FTP,"');
			array_push($ActionArray, ';;');
			array_push($ActionArray, 'esac');
			array_push($ActionArray, 'done');

			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		public function getWebServer(){
			$filename = "getWebServer.sh";
			$ActionArray[] = "Sites=($(ls /etc/apache2/sites-available/))";
			array_push($ActionArray, 'for i in ${Sites[*]}; do');
			array_push($ActionArray, '	ServerName=$(cat /etc/apache2/sites-available/$i | grep "ServerName" | cut -d " " -f2 | tail -n1)');
			array_push($ActionArray, '	SitesEnable=$(ls /etc/apache2/sites-enabled/ | grep "$i")');
			array_push($ActionArray, '	if [[ $SitesEnable == "" && $ServerName == "" ]]; then');
			array_push($ActionArray, '		echo "$i|No identificado|No habilitado,"');
			array_push($ActionArray, '	else');
			array_push($ActionArray, '		echo "$i|$ServerName|Habilitado,"');
			array_push($ActionArray, '	fi');
			array_push($ActionArray, 'done');
			array_push($ActionArray, 'echo "="');
			array_push($ActionArray, "NumAccesos=$(cat /var/log/apache2/access.log | wc -l)");
			array_push($ActionArray, "ConHttp=$(lsof -i -nP | egrep '(CONNECTED|ESTAB)' | grep '80' | wc -l)");
			array_push($ActionArray, "ConHttps=$(lsof -i -nP | egrep '(CONNECTED|ESTAB)' | grep '443' | wc -l)");
			array_push($ActionArray, "TimeWaitHttp=$(lsof -i -nP | grep TIME_WAIT | grep '80' | wc -l)");
			array_push($ActionArray, "TimeWaitHttps=$(lsof -i -nP | grep TIME_WAIT | grep '443' | wc -l)");
			array_push($ActionArray, "APID=$(ps axo pid,cmd,user | grep apache2 | grep root | grep -v $0 | grep -v g | cut -d' ' -f2)");
			array_push($ActionArray, "DateInit=$(ls -od --time-style=+%d-%m-%y,%H-%M /proc/$APID | cut -d' ' -f5)");
			array_push($ActionArray, "CantRestart=$(cat /var/log/apache2/*.log | grep 'resuming normal operations' | wc -l)");
			array_push($ActionArray, 'echo "$NumAccesos,$ConHttp,$ConHttps,$TimeWaitHttp,$TimeWaitHttps,$DateInit,$CantRestart,"');

			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}

		/*public function getDHCPServer(){
			$filename = "getDHCPServer.sh";
			$ActionArray[] = 'IntListen=($(cat /etc/default/isc-dhcp-server | grep INTERFACES | cut -d " -f2))';
			array_push($ActionArray, 'echo "${IntListen[*]},"');
			array_push($ActionArray, 'echo "="');
			array_push($ActionArray, 'Fichero="/var/lib/dhcp/dhcpd.leases"');
			array_push($ActionArray, "Leases=($(cat $Fichero | grep lease | sed '1,2d' | tr -d '{' | cut -d ' ' -f2))");
			array_push($ActionArray, 'if [[ ${Leases[*]} != "" ]]; then');
			array_push($ActionArray, '	for i in ${Leases[*]}; do');
			array_push($ActionArray, "		Mac=$(cat $Fichero | grep -A7 $i | grep ethernet | awk {'print $3'} | tr -d ';')");
			array_push($ActionArray, '		echo "$i,$Mac,"');
			array_push($ActionArray, "	done");
			array_push($ActionArray, 'fi');
			
			$RL[] = $this->remote_path.$filename;
			array_push($RL, "rm -rf ".$this->remote_path.$filename);
			if ($this->writeFile($ActionArray, $filename) && $this->sendFile($filename))
				return $this->RunLines(implode("\n", $RL));
			return getErrors();
		}*/

		public function ConnectDB($H, $U, $P, $D, $X){
			$this->db_connect = new GNet($H, $U, $P, $D);
			$this->db_prefix = $X;
		}

	}
	// echo (new ConnectSSH("192.168.100.2", "network", "123"))->getDHCPShowAssignIP();

?>