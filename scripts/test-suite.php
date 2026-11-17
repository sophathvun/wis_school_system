<?php

// Each file gets a fresh application/process and temporary disks. School data is never used.
$root=dirname(__DIR__);
chdir($root);
if(is_file($root.'/bootstrap/cache/config.php')){
    fwrite(STDERR,"Clear the local Laravel configuration cache before testing.\n");exit(1);
}
$filter='*Test.php';foreach(array_slice($argv,1) as $argument){
    if(str_starts_with($argument,'--filter='))$filter=substr($argument,9);
    else {fwrite(STDERR,"Use --filter=<file-name-pattern> to select test files.\n");exit(1);}
}
$files=array_merge(glob($root.'/tests/Unit/*Test.php'),glob($root.'/tests/Feature/*Test.php'));
$files=array_values(array_filter($files,fn($file)=>fnmatch($filter,basename($file))));sort($files);
if(!$files){fwrite(STDERR,"No matching test files.\n");exit(1);}
$run=date('Ymd-His').'-'.getmypid();$directory=$root.'/storage/logs/test-suite-'.$run;
if(!mkdir($directory,0750,true))throw new RuntimeException('Cannot create test log directory.');
foreach(['APP_ENV'=>'testing','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:','DB_URL'=>'','TEST_TOKEN'=>'school-'.$run] as $key=>$value)putenv($key.'='.$value);
// Windows PHP distributions bundle OpenSSL's config without always exporting its path.
$opensslConfig=dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
if(PHP_OS_FAMILY==='Windows'&&!getenv('OPENSSL_CONF')&&is_file($opensslConfig))putenv('OPENSSL_CONF='.$opensslConfig);
$results=[];$failures=0;
foreach($files as $file){
    $log=$directory.'/'.basename(dirname($file)).'-'.basename($file,'.php').'.log';
    $process=proc_open([PHP_BINARY,'-d','memory_limit=512M',$root.'/vendor/pestphp/pest/bin/pest',$file],[0=>['pipe','r'],1=>['file',$log,'w'],2=>['file',$log.'.stderr','w']],$pipes,$root);
    if(!is_resource($process))throw new RuntimeException('Cannot start test process.');
    fclose($pipes[0]);$exit=proc_close($process);$summary=null;
    foreach(file($log,FILE_IGNORE_NEW_LINES)?:[] as $line)if(str_starts_with($line,'{"tool":"pest"'))$summary=json_decode($line,true,512,JSON_THROW_ON_ERROR);
    $summary??=['result'=>'failed','tests'=>0,'passed'=>0,'message'=>'Process exited without a test summary.'];
    if($exit!==0)$failures++;
    $results[]=['file'=>basename($file),'exit_code'=>$exit,'summary'=>$summary,'log'=>$log];
    file_put_contents($directory.'/results.json',json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    echo basename($file).': '.$summary['result'].' ('.($summary['passed']??0).'/'.($summary['tests']??0).')'.PHP_EOL;
}
echo 'Completed '.count($files).' files; '.$failures.' failed. Logs: '.$directory.PHP_EOL;
exit($failures?1:0);
