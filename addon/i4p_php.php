#!/usr/bin/php
<?php
# Инициализация
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('/usr/local/ispmgr/lib/php/isp4private/core.php');
$isp = new PiminoffISP("i4p_php",true,3,1);
$p = $isp->getParams();
$form = new XMLData();
$form->addNode($form->root,"metadata");
$isp->logs->WriteLog(3,json_encode($p));
$isp->checkLib("I4P-Lib-EAC");
require_once '/usr/local/ispmgr/lib/php/isp4private/libs/I4P-Lib-EAC.php';
switch($isp->func){
    case "i4p.www.php":{
		$form->mode = "form";
		$form->addArray($form->root->metadata,$isp->getMetaData());
		$slist = $form->addNode($form->root,"slist");
		$form->addAttribute($slist,"name","php");
		
		if (isset($p["elid"])){
			if (!is_file("/etc/httpd/conf/i4pmgr.conf") && !is_file($isp->config->get("File","General"))) $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_err_file","Не удалось считать файлы настроек домена.","Lang"),$isp->tools->filterStr($p["elid"]));
		    $file = is_file("/etc/httpd/conf/i4pmgr.conf")?'/etc/httpd/conf/i4pmgr.conf':$isp->config->get("File","General");
			$eac = new I4PLib_EAC($file);
		    $SUG = $eac->getConfig($isp->tools->filterStr($p["elid"]),"SuexecUserGroup");
		    if (!$SUG){
		        # Ищем данные по SuexecUserGroup - Они появяться если домен работает в режиме CGI
		        $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_owner_404","Не удалось определить владельца домена, попробуйте включить PHP-CGI для домена.","Lang"),$isp->tools->filterStr($p["elid"]));
		    }
		    # Теперь распарсим строку, [0] Пользователь | [1] Группа
		    $ex = explode(" ",$SUG);
		    # Доступ только для РУТА и для текущего пользователя к домену
		    if ($isp->env->login != "root" && $isp->env->login != $ex[0]) $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_owner_403","У вас нет доступа к этому домену.","Lang"),$isp->tools->filterStr($p["elid"]));
		    # Теперь ищем бинарник 
		    $bin = $eac->getConfig($isp->tools->filterStr($p["elid"]),"ScriptAlias");
		    # Так как записей этих может быть несколько, оно отключит CGI, а оставит только PHP, при записи файлов, на данный момент происходит только чтение!
		    if (!$bin) $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_404_bin","Ошибка, возможно PHP-CGI отключено! Для продолжения, необходимо включить PHP-CGI.","Lang"),$isp->tools->filterStr($p["elid"]));
		    
			if (isset($p["sok"]) && $p["sok"] == "ok"){
			    # Проверяем и получаем наличие бинарника по ключу
			    if (!isset($p["php"]) || $p["php"] == "" || $isp->config->get("bin",$isp->tools->filterStr($p["php"])) == null)  $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_elem_404","Данная версия PHP не найдена.","Lang"),$isp->tools->filterStr($p["elid"]));
			    $bin_php = $isp->config->get("bin",$isp->tools->filterStr($p["php"]));
			    # Создаем папку, для привязки PHP к домену
			    if (!is_dir("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/") && !mkdir("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/", 0700, true)) $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_err_save","Не удалось сохранить ваши данные.","Lang"),$isp->tools->filterStr($p["elid"]));
			    # Проверяем наличие PHP.ini и копируем его
			    if (!is_file("/var/www/php-bin/".$ex[0]."/php.ini")) $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_err_read_ini","Не удалось считать настройки PHP.","Lang"),$isp->tools->filterStr($p["elid"]));
			    if (false == true){
			        # Отключил, так как Apache не дает, по непонятным мне причинам использовать папку, как php-bin/
			        # Если у вас есть решение, буду благодарен в помощи
			        # Для связи: https://github.com/pimnik98/I4P-PHP
    			    $php_ini = copy("/var/www/php-bin/".$ex[0]."/php.ini", "/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/php.ini");
    			    $fd = fopen("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/php", 'w');
                    fwrite($fd, "#!".$bin_php);
                    fclose($fd);
                    chown("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/php", $ex[0]);
                    chown("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/php.ini", $ex[0]);
                    chown("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/", $ex[0]);
                    chmod("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/php",0555);
                    chmod("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/php",0555);
                    chmod("/var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/php",0555);
                    $eac->setConfig($isp->tools->filterStr($p["elid"]),"ScriptAlias","/php-bin/ /var/www/php-bin/".$ex[0]."/".$isp->tools->filterStr($p["elid"])."/");
                    $eac->save();
			    } else {
			        # Пока делаем для всего пользователя
			        $fd = fopen("/var/www/php-bin/".$ex[0]."/php", 'w');
                    fwrite($fd, "#!".$bin_php);
                    fclose($fd);
                    # Выведем предупреждение о том, что данные приняты для всех доменов пользователя.
                    $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_save_global","Настройки сохранены, но по техническим причинам, смена версии произошла для всего пользователя. Приносим свои извинения.","Lang"),$isp->tools->filterStr($p["elid"]));
			    }
			    # Если все ОК, закроем форму
				$form->addNode($form->root,"ok","ok");
			}
			
			$count = 0;
			foreach($isp->config->toArray(true) as $key => $value){
			    # Пропускаем, позиции где нет значений bin и name
			    if (!isset($value["bin"]) || !isset($value["name"]) || !is_file($value["bin"])) continue;
			    $v = $form->addNode($slist,"msg",$value["name"]);
		        $form->addAttribute($v,"key",$key);
		        $count++;
			}
			
			if ($count == 0) $isp->err->FormError($isp->func,$isp->config->def($isp->env->lang."_msg_php_404","На сервере не установлены альтернативные версии PHP","Lang"),$isp->tools->filterStr($p["elid"]));
			$form->addNode($form->root,"elid",$isp->tools->filterStr($p["elid"]));
		}
		$form->PrintXML();
		break;	       
    }
    case "i4p.php.list":{
        $form->mode = "table";
		$form->addArray($form->root->metadata,$isp->getMetaData());
		foreach($isp->config->toArray(true) as $vk => $vv){
			if (!isset($vv["bin"]) || !isset($vv["name"])) continue;
		    $elem = $form->addNode($form->root,"elem");
		    $form->addNode($elem,"id",$vk);
    		$form->addNode($elem,"name",$vv["name"]);
    		$form->addNode($elem,"bin",$vv["bin"]);
    		if (!is_file($vv["bin"])){
        		$form->addNode($elem,"err_binary",$isp->config->def($isp->env->lang."_notify_err_binary","У данной версии отсутствует бинарник или он имеет некорретный формат, он не будет доступен для использования!","Lang"));
    		} else {
    		    $form->addNode($elem,"v",$isp->config->def($isp->env->lang."_notify_v","Данная версия активирована!","Lang"));
    		}
		}
		$form->PrintXML();
    }
	default:{
		$isp->err->DirectError("The function (".$isp->func.") is not detected or has not yet been implemented in this version of the plugin.");
	}
}

?>
