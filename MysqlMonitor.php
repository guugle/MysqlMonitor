<?php


new Monitor(array(
    'host' => 'localhost',
    'port' => 3306,
    'user' => 'root',
    'pass' => 'root',
));

class Monitor {

    var $model;

    var $refresh = 5;

    public function __construct($config) {
        $this->init($config);
    }

    private function header() {
        header("Content-Type:text/html; charset=utf-8");
        header("X-Powered-By:PHP");
    }

    private function init($config) {
        $this->model = new Model($config);
        if(function_exists('date_default_timezone_set')) {
            @date_default_timezone_set("Etc/GMT-8");
        }
        $this->header();
        $this->execute();
    }

    private function execute() {
        $data = array();
        $data['dblist'] = $this->model->getDbLists();
        $data['process'] = $this->model->getProcessLists();
        $data['server'] = $this->model->getServerInfo();       
        $data['var'] = $this->model->getServerVar();
        $this->view($data);
    }

    private function view($data) {
        echo "<html><head><title>MySQLMonitor - 简易MySQL监控程序</title><meta http-equiv=\"refresh\" content='". $this->refresh ."' />";
        echo "<style>body,table,td,tr{margin:0px; padding:0px;font-size:13px;word-wrap:break-word;font-family: tahoma,arial}.wrap{ margin:0 auto; width:90%}table{border-collapse: collapse;border-spacing: 0;box-shadow: 1px 1px 1px #CCCCCC;clear: both;margin: 0 0 10px;padding: 0; width:100%}th{ background: none repeat scroll 0 0 #DEDEDE;border: 1px solid #CCCCCC;color: #626262;font-weight: bold;padding: 3px 6px;text-align: left;}tr{background: none repeat scroll 0 0 #FFFFFF;padding: 0;}td{border: 1px solid #CCCCCC;padding: 3px 6px;}</style>";
        echo "</head><body><div class='wrap'>";
        //serverinfo
        $this->serverInfoView($data['server']);
        //performance
        $this->performanceView($data['var'], $data['server']);
        //dblist
        $this->dbListsView($data['dblist']);
        //show processlist
        $this->processView($data['process']);

        echo "</div></body></html>";
    }

    private function performanceView($p, $s) {
        echo "<table><tr><th colspan=4>系统监控</th></tr>";
        echo "<tr><td width='13%'>慢查询数量</td><td width='37%'>{$s['Slow_queries']} (大于{$p['slow_launch_time']}s的查询)</td><td width='13%'>慢查询日志</td><td width='37%'>".($p['slow_query_log'] == 'OFF' ? '未开启 (建议开启)' : $p['slow_query_log_file']) ."</td></tr>";
        echo "<tr><td width='13%'>最大连接数</td><td width='37%'>{$p['max_connections']}</td><td width='13%'>利用率</td><td width='37%'> (最大并发连接数÷最大连接数) * 100% = ". (round($s['Max_used_connections']/$p['max_connections'], 4)*100) ."%</td></tr>";
        if( $s['Key_read_requests'] ) {
            echo "<tr><td width='13%'>索引命中率</td><td width='37%'>Key_reads({$s['Key_reads']}) / Key_read_requests({$s['Key_read_requests']}) * 100% = ".(round($s['Key_reads']/$s['Key_read_requests'], 4) * 100)."%</td><td width='13%'>索引缓冲尺寸</td><td width='37%'>{$p['key_buffer_size']}</td></tr>";
        }
        echo "<tr><td width='13%'>MyISAM索引缓存</td><td width='37%'>{$p['key_buffer_size']}</td><td width='13%'>索引未命中缓存概率</td><td width='37%'> ". ($s['Key_read_requests'] ?  "(从硬盘读取索引数{$s['Key_reads']}÷索引读取请求数{$s['Key_read_requests']}) * 100% = ". (round($s['Key_reads']/$s['Key_read_requests'], 4)*100) ."% ,如果过大须考虑加大key_buffer_size的值" : '0%' )."</td></tr>";
        echo "<tr><td width='13%'>缓存簇(blocks)利用率</td><td colspan=3>曾经用到的最大的blocks数{$s['Key_blocks_used']}÷(未使用的blocks数{$s['Key_blocks_unused']}+曾经用到的最大的blocks数{$s['Key_blocks_used']}) * 100% = ".(round($s['Key_blocks_used']/($s['Key_blocks_unused'] + $s['Key_blocks_used']), 4) * 100)."% (80%最佳)</td></tr>";
        echo "<tr><td width='14%'>临时表</td><td colspan=3>创建临时表数{$s['Created_tmp_tables']}, 其中在磁盘上创建临时表数{$s['Created_tmp_disk_tables']}, 临时文件数{$s['Created_tmp_files']}, 创建磁盘临时表数/临时表总数 * 100% = ".(round($s['Created_tmp_disk_tables']/$s['Created_tmp_tables'], 4)*100)."% (应尽量低于25%)</td></tr>";
        echo "<tr><td width='14%'>临时表尺寸</td><td colspan=3>临时表的最大尺寸tmp_table_size={$p['tmp_table_size']}, 独立的内存表所允许的最大容量 max_heap_table_size={$p['max_heap_table_size']} </td></tr>";
        echo "<tr><td width='14%'>Open Table</td><td colspan=3>打开表的数量Open_tables={$s['Open_tables']}, 打开过的表数量Opened_tables={$s['Opened_tables']}, table_open_cache为{$p['table_open_cache']}, Open_tables/Opened_tables * 100% =".(round($s['Open_tables']/$s['Opened_tables'], 4) * 100)."%(尽量>=85%),Open_tables/table_open_cache * 100% = ".(round($s['Open_tables']/$p['table_open_cache'], 4)*100)."%(尽量<=95%)</td></tr>";
        echo "<tr><td width='14%'>进程使用情况</td><td colspan=3>Threads_cached={$s['Threads_cached']},Threads_connected={$s['Threads_connected']},Threads_created={$s['Threads_created']},Threads_running={$s['Threads_running']},thread_cache_size={$p['thread_cache_size']} ,如果创建线程数Threads_created过大的话可适当增大thread_cache_size的值</td></tr>";
        echo "</table>";
    }

    private function serverInfoView($info) {
        echo "<table><tr><th colspan=4>服务器信息</th></tr>";
        $day = floor($info['Uptime']/86400);
        $hour = floor(($info['Uptime'] % 86400)/3600);
        $minute = floor((($info['Uptime'] % 86400) % 3600)/60);
        $second = (($info['Uptime'] % 86400) % 3600) % 60;
        echo "<tr><td width='13%'>服务器启动时间</td><td width='37%'>". date('Y 年 m 月d 日 H:i:s' ,time()-$info['Uptime']) ."</td><td width='13%'>服务器运行时间</td><td width='37%'>{$day} 天 {$hour} 小时 {$minute}分 {$second}秒</td></tr>";
        echo "<tr><td width='13%'>已接收流量</td><td width='37%'>".round($info['Bytes_received']/1024, 2)."KB</td><td width='13%'>已发送流量</td><td width='37%'>".round($info['Bytes_sent']/1024 ,2)."KB</td></tr>";
        echo "<tr><td width='13%'>最大并发连接数</td><td width='37%'>".$info['Max_used_connections']."</td><td width='13%'>连接失败数</td><td width='37%'>{$info['Aborted_connects']}</td></tr>";
        echo "<tr><td width='13%'>总共连接数</td><td colspan=3>{$info['Connections']}</td></tr>";
        echo "</table>";
    }

    private function dbListsView($dblist) {
        echo "<table><tr><th>数据库列表(共". count($dblist) ."个)</th></tr>";
        echo "<tr><td>".implode('　', $dblist)."</td></tr></table>";
    }

    private function processView($process) {
        echo "<table><tr><th colspan=8>Show Process(共". count($process) ."个)</th></tr>";
        echo "<tr><td>ID</td><td>用户</td><td>主机</td><td>数据库</td><td>命令</td><td>时间</td><td>状态</td><td>SQL查询</td></tr>";
        foreach($process as $p) {
            echo "<tr><td>{$p['Id']}</td><td>{$p['User']}</td><td>{$p['Host']}</td><td>{$p['db']}</td><td>{$p['Command']}</td><td>{$p['Time']}</td><td>{$p['State']}</td><td>{$p['Info']}</td></tr>";
        }
        echo "</table>";
    }
}

class Mysql {

    var $version = '';

    var $link = NULL;

    var $config = array(
        'host' => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'pass' => 'root',
        'charset' => 'utf8',
    );

    private function connect() {
        if(NULL === $this->link || mysql_ping($this->link)) {
            $this->link = mysql_connect($this->config['host'].':'.$this->config['port'], $this->config['user'], $this->config['pass']);
            if(!$this->link) {
                die("Could not connect Mysql:".mysql_error());
            }
            $this->verson = mysql_get_server_info($this->link);
            /*if($this->version >= '4.1' ) {
                mysql_query("SET NAMES '{$config['charset']}'", $this->link);
            }*/
            if($this->version >= '5.0.1') {
                mysql_query("SET sql_mode=''", $this->link);
            }
        }
    }

    public function query($sql) {
        $this->connect();
        $result = array();
        if($query = mysql_query($sql, $this->link)) {
            if(mysql_num_rows($query) >0) {
                while($row = mysql_fetch_assoc($query)){
                    $result[] = $row;
                }
            }
            mysql_free_result($query);
        } else {
            die("Mysql query error:".mysql_error());
        }
        return $result;
    }
}

class Result extends Mysql {

    public function getFirstRow($sql) {
        $sql .= " LIMIT 1";
        $result = $this->query($sql);
        return isset($result[0]) ? $result[0] : array();
    }

    public function getFirstCol($sql) {
        $result = $this->getFirstRow($sql);
        return array_shift($result);
    }
}

class Model extends Result {

    public function __construct($config) {
        $this->config = $config;
    }

    public function getDbLists() {
        $sql = "SHOW DATABASES";
        $dbs = $this->query($sql);
        $db = array();
        foreach($dbs as $v) {
            $db[] = $v['Database'];
        }
        return $db;
    }

    public function getProcessLists() {
        $sql = "SHOW PROCESSLIST";
        return$this->query($sql);
    }

    public function getServerInfo() {
        $sql = "SHOW GLOBAL STATUS";
        $info = $this->query($sql);
        $server = array();
        foreach($info as $s) {
            $server[$s['Variable_name']] = $s['Value'];
        }
        return $server;
    }

    public function getServerVar() {
        $sql = "SHOW VARIABLES";
        $info = $this->query($sql);
        $p = array();
        foreach($info as $s) {
            $p[$s['Variable_name']] = $s['Value'];
        }
        return $p;
    }
}
