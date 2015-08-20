# Universal Server Module
The Universal Server Module brings the possibility to manage orders and integrates a Dedicated Server manager solution to Blesta.

With the Universal Server Module you can Manage Dedicated Servers and Colocation, With a powerful integreation over Secure Shell, You can Reboot and turn off the server,
Also can make changes from Blesta directly to the Server, Like Update Software, Change Root Software and Change Hostname.

The Universal Server Module, can shows the system status and statistics directly from the server.

<b>Features:</b>
<ul>
  <li>Colocation Manager</li>
  <li>Client and Admin Side Information</li>
  <li>Web SSH Client</li>
  <li>Web FTP Client</li>
  <li>Statistics</li>
  <li>Off and Reset Button function without API</li>
  <li>Live Screenshot</li>
  <li>Automatic Detection of Control Panel</li>
  <li>Colocation Tab hides automatically if you don't use anything colocation parameter</li>
  <li>If you don't provide an API for the Off and Reset Button, automatically the module uses SSH for make this actions.</li>
  <li>The On Button only appears if you are defined an API</li>
  <li>Requires SSH2 Extension of PHP Installed</li>
  <li>Update Software via Client Area</li>
  <li>Change Root password via Client Area</li>
  <li>Change Hostname via Client Area</li>
</ul>

#Documentation
 Parameter     | Description 
 ------------- | ------------- 
 ded_os        | The Operative Systems List, Allow centosX, fedoraX, ubuntuX, freebsdX. X = Version
 ded_hdd       | The Hard Disk size, Allows HDD and SSD and any custom value in GB and TB, Example: 1tbssd, 12gbhdd. 
 ded_cpu       | You need provide the string with the full name of the CPU. 
 ded_ram       | RAM of the server, Allos any value in GB. Example: 128gbRAM
 ded_network   | Bandwidth and Network Speed, Allows any value in Gbps or Mbps for the speed. The bandwidth its allowed only in GB. Example: 100mbpsNET[500]NET
 ded_ips       | You can add the IP's of the server after provides to your client. You can add one IP addres or a Range. Example: 8.8.8.8 - 8.8.8.255
 ded_hostname  | A Valid FQDN Hostname.
 root_pass     | Root Password for the server.
 control_panel | (Optional) The URL of the control panel.
 <b>Colocation</b> | <b>All Colocation Parameters are Optional.</b>
 colo_pos      | Position of the server in the Rack. Only <20 values.
 colo_weight   | Weight of the server.
 colo_datacenter | The Datacenter name.
 colo_floor    | The floor of the room.
 colo_room     | The room of the rack.
 colo_rack     | The name of the rack.
 
 
 
