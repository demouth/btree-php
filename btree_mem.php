<?php
include_once './btree.php';

ini_set('memory_limit', '512M');

$vals = [];
for($i=0;$i<1000000;$i++) $vals[] = random_int(0, 100000000);


$bt = new BTree(3);
foreach ($vals as $val){
    $bt->ReplaceOrInsert( new Number($val) );
}

var_dump($bt->Has(new Number(10)));
echo $bt->root->print(1);


function m(){
    return (memory_get_peak_usage(true)/1024)."\n";
}
