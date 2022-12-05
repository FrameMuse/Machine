<?php 
declare(ticks=1); 

class JobDaemon{ 

    public $maxProcesses = 25; 
    protected $jobsStarted = 0; 
    protected $currentJobs = array(); 
    protected $signalQueue=array();   
    protected $parentPID; 
   
    public function __construct(){ 
        echo "constructed \n"; 
        $this->parentPID = getmypid(); 
        pcntl_signal(SIGCHLD, array($this, "childSignalHandler")); 
    } 
   
    /** 
    * Run the Daemon 
    */ 
    public function run($func, $times){ 
        echo "Running \n"; 

        for($i=0; $i<$times; $i++){
            $jobID = mt_rand(11111, 999999999);

            while(count($this->currentJobs) >= $this->maxProcesses){ 
               print "Maximum children allowed, waiting...\n"; 
               sleep(1); 
            } 

            $launched = $this->launchJob($jobID, $func);
        }

        #while (count($this->currentJobs)) { 
            #echo "Waiting for current jobs to finish... \n"; 
            #sleep(1); 
        #} 
    } 

    protected function launchJob($jobID, $func){ 
        $pid = pcntl_fork(); 
        if($pid == -1){ 
            error_log('Could not launch new job, exiting'); 
            return false; 
        } 
        else if ($pid){ 
            $this->currentJobs[$pid] = $jobID; 
            if(isset($this->signalQueue[$pid])){ 
                echo "found $pid in the signal queue, processing it now \n"; 
                $this->childSignalHandler(SIGCHLD, $pid, $this->signalQueue[$pid]); 
                unset($this->signalQueue[$pid]); 
            } 
        } 
        else{ 
            $func();
            $exitStatus = 901;
            exit($exitStatus); 
        } 
        return true; 
    } 
   
    public function childSignalHandler($signo, $pid=false, $status=null){ 
       
        if(!$pid) $pid = pcntl_waitpid(-1, $status, WNOHANG); 
       
        while($pid > 0) { 
            if($pid && @isset($this->currentJobs[$pid])){ 
                unset($this->currentJobs[$pid]); 
            } elseif($pid) @$this->signalQueue[$pid['pid']] = $status; 
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        } 
        return true; 
    } 
}
