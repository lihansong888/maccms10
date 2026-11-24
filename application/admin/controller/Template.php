<?php
namespace app\admin\controller;
use think\Db;

class Template extends Base
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 将模板文件路径限制在真实 template 目录内，防止 ../ 与符号链接逃逸。
     * $allowMissing 仅允许目标文件不存在、但父目录必须真实存在的情况（编辑器首次保存）。
     */
    private function resolveTemplateFilePath($path, $allowMissing = false)
    {
        $path = str_replace('\\', '/', (string)$path);
        if (strpos($path, './template/') !== 0) {
            return false;
        }

        $relative = substr($path, 2);
        $segments = explode('/', $relative);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        $root = realpath(ROOT_PATH . 'template');
        if ($root === false) {
            return false;
        }

        $candidate = ROOT_PATH . $relative;
        $real = realpath($candidate);
        if ($real === false) {
            if (!$allowMissing) {
                return false;
            }
            $parent = realpath(dirname($candidate));
            if ($parent === false || !is_dir($parent)) {
                return false;
            }
            $real = $parent . DIRECTORY_SEPARATOR . basename($candidate);
        } elseif (!is_file($real)) {
            return false;
        }

        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($real, $prefix) !== 0) {
            return false;
        }

        return $real;
    }

    public function index()
    {
        $param = input();
        $path = isset($param['path']) && is_string($param['path']) ? $param['path'] : '';
        $path = str_replace('\\','',$path);
        $path = str_replace('/','',$path);

        if(empty($path)){
            $path = '.@template';
        }

        if (substr($path, 0, 10) !== ".@template") {
            $path = ".@template";
        }
        // 目录层级只允许 template 根目录下的普通名称，拒绝 ..、空段和伪造路径。
        $relativeParts = substr($path, 10);
        if ($relativeParts !== '') {
            $relativeParts = explode('@', ltrim($relativeParts, '@'));
            foreach ($relativeParts as $part) {
                if ($part === '' || $part === '.' || $part === '..') {
                    $path = ".@template";
                    break;
                }
            }
        }

        $uppath = substr($path,0,strrpos($path,"@"));
        $ischild = 0;
        if ($path !=".@template"){
            $ischild = 1;
        }

        $config = config('maccms.site');
        if($param['current']==1){
            $path = '.@template@' . $config['template_dir'] .'@' . $config['html_dir'] ;
            $ischild = 0;
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/*';
        }
        elseif($param['label']==1){
            $path = '.@template@' . $config['template_dir'] .'@' . $config['html_dir'] ;
            $ischild = 0;
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/label/*';
        }
        elseif($param['ads']==1){
            $path = '.@template@' . $config['template_dir'] .'@' . $config['html_dir'] ;
            $ischild = 0;
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/ads/*';
        }
        else{
            $pp = str_replace('@','/',$path);
            $filters = $pp.'/*';
        }

        // 真实路径校验：即使配置/请求中的 @ 分隔路径包含符号链接或目录穿越，
        // 模板管理器也只能枚举 template 根目录以内的真实目录。
        $templateRoot = realpath(ROOT_PATH . 'template');
        $realDirectory = realpath(ROOT_PATH . substr($pp, 2));
        $templatePrefix = $templateRoot === false ? '' : rtrim($templateRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($templateRoot === false || $realDirectory === false || !is_dir($realDirectory)
            || ($realDirectory !== $templateRoot && strpos($realDirectory, $templatePrefix) !== 0)) {
            $path = '.@template';
            $uppath = '';
            $ischild = 0;
            $pp = './template';
            $filters = $pp . '/*';
        }

        $this->assign('curpath',$path);
        $this->assign('uppath',$uppath);
        $this->assign('ischild',$ischild);

        $num_path = 0;
        $num_file = 0;
        $sum_size = 0;
        $files = [];

        if(is_dir($pp)) {
            $farr = glob($filters);
            if ($farr) {
                foreach ($farr as $f) {

                    if(is_dir($f)) {
                            $num_path++;
                            $tmp_path = str_replace('./template/', '.@template/', $f);
                            $tmp_path = str_replace('/', '@', $tmp_path);
                            $tmp_name = str_replace($path . '@', '', $tmp_path);
                            $ftime = filemtime($f);

                            $files[] = ['isfile' => 0, 'name' => $tmp_name, 'path' => $tmp_path, 'note'=>lang('dir'), 'time' => $ftime];
                    }
                    elseif(is_file($f)) {
                        $num_file++;
                        $fsize = filesize($f);
                        $sum_size += $fsize;
                        $fsize = mac_format_size($fsize);
                        $ftime = filemtime($f);
                        $tmp_path = mac_convert_encoding($f, "UTF-8", "GB2312");

                        $path_info = @pathinfo($f);
                        $tmp_path = $path_info['dirname'];
                        $tmp_name = $path_info['basename'];

                        $files[] = ['isfile' => 1, 'name' => $tmp_name, 'path' => $tmp_path, 'fullname'=> $tmp_path.'/'.$tmp_name, 'size' => $fsize,'note'=>lang('file'), 'time' => $ftime];
                    }
                }
            }
        }
        $this->assign('sum_size',mac_format_size($sum_size));
        $this->assign('num_file',$num_file);
        $this->assign('num_path',$num_path);
        $this->assign('files',$files);

        $this->assign('title',lang('admin/template/title'));
        return $this->fetch('admin@template/index');
    }

    public function ads()
    {
        $adsdir = $GLOBALS['config']['site']['ads_dir'];
        if(empty($adsdir)){
            $adsdir='ads';
        }
        $path = './template/'.$GLOBALS['config']['site']['template_dir'].'/'.$adsdir ;
        if(!file_exists($path)){
            mac_mkdirss($path);
        }

        $filters = $path.'/*.js';
        $num_file=0;
        $sum_size=0;
        $farr = glob($filters);
        if ($farr) {
            foreach ($farr as $f) {
                if(is_file($f)) {
                    $num_file++;
                    $fsize = filesize($f);
                    $sum_size += $fsize;
                    $fsize = mac_format_size($fsize);
                    $ftime = filemtime($f);
                    $tmp_path = mac_convert_encoding($f, "UTF-8", "GB2312");

                    $path_info = @pathinfo($f);
                    $tmp_path = $path_info['dirname'];
                    $tmp_name = $path_info['basename'];

                    $files[] = ['isfile' => 1, 'name' => $tmp_name, 'path' => $tmp_path, 'fullname'=> $tmp_path.'/'.$tmp_name, 'size' => $fsize,'note'=>lang('file'), 'time' => $ftime];
                }
            }
        }
        $this->assign('curpath',$path);
        $this->assign('sum_size',mac_format_size($sum_size));
        $this->assign('num_file',$num_file);
        $this->assign('files',$files);
        $this->assign('title',lang('admin/template/ads/title'));
        return $this->fetch('admin@template/ads');
    }

    public function info()
    {
        $param = input();

        $fname = isset($param['fname']) ? (string)$param['fname'] : '';
        $fpath = isset($param['fpath']) ? (string)$param['fpath'] : '';

        if ($fpath === '' || $fname === '' || strpos($fname, '/') !== false || strpos($fname, '\\') !== false) {
            $this->error(lang('param_err').'1');
            return;
        }
        $fpath = str_replace('@', '/', $fpath);
        $fpath = str_replace('\\', '/', $fpath);
        $fullname = rtrim($fpath, '/') . '/' . $fname;
        $fullname = str_replace('\\', '/', $fullname);
        $fullname = $this->resolveTemplateFilePath($fullname, true);
        if ($fullname === false) {
            $this->error(lang('param_err').'2');
            return;
        }

        $path = pathinfo($fullname);
        $extarr = array('html', 'htm', 'js', 'xml');
        $extension = isset($path['extension']) ? strtolower($path['extension']) : '';
        if (!in_array($extension, $extarr, true)) {
            $this->error(lang('admin/template/ext_safe_tip'));
            return;
        }

        $filter = '<\?|php|eval|server|assert|get|post|request|cookie|session|input|env|config|call|global|dump|print|phpinfo|fputs|fopen|global|chr|strtr|pack|system|gzuncompress|shell|base64|file|proc|preg|call|ini|exec\s*\(|passthru\s*\(|popen\s*\(|show_source\s*\(|create_function\s*\(|register_shutdown_function\s*\(|register_tick_function\s*\(|error_log\s*\(|{:|{$|{~|{-|{+|{/';
        $this->assign('filter',$filter);

        if (Request()->isPost()) {
            $validate = \think\Loader::validate('Token');
            if(!$validate->check($param)){
                return $this->ajaxErrorWithFreshToken($validate->getError());
            }

            $validate = \think\Loader::validate('Template');
            if(!$validate->check($param)){
                return $this->ajaxErrorWithFreshToken($validate->getError());
            }

            $fcontent = isset($param['fcontent']) && is_string($param['fcontent']) ? $param['fcontent'] : '';
            // ThinkPHP 标签允许 value/condition 等属性以冒号开头执行原始 PHP 表达式；
            // 黑名单无法阻止 'sy'.'stem' 这类字符串拼接绕过，编辑器因此禁止标签属性中的直接表达式。
            if (preg_match("/\\{[a-z][^}]*=\\s*([\"'])\\s*:/is", $fcontent)) {
                $this->error(lang('admin/template/php_safe_tip'));
                return;
            }
            $r = mac_reg_replace($fcontent,$filter,"*");
            if($fcontent !== $r){
                $this->error(lang('admin/template/php_safe_tip'));
                return;
            }
            $handle = @fopen($fullname, 'wb');
            if ($handle === false) {
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            $res = fwrite($handle, $fcontent);
            fclose($handle);

            if($res===false){
                return $this->ajaxErrorWithFreshToken(lang('save_err'));
            }
            return $this->success(lang('save_ok'));
        }

        $fcontent = @file_get_contents($fullname);
        $fcontent = str_replace('</textarea>','<&#47textarea>',$fcontent);
        $this->assign('fname',$fname);
        $this->assign('fpath',$fpath);
        $this->assign('fcontent',$fcontent);

        return $this->fetch('admin@template/info');
    }

    public function del()
    {
        if (!Request()->isPost() || !$this->adminCsrfOk()) {
            return $this->error(lang('token_err'));
        }
        $param = input();
        $fname = isset($param['fname']) ? $param['fname'] : [];
        if (!is_array($fname)) {
            $fname = [$fname];
        }
        foreach ($fname as $a) {
            $target = $this->resolveTemplateFilePath($a, false);
            if ($target !== false && is_file($target)) {
                @unlink($target);
            }
        }
        return $this->success(lang('del_ok'));
    }

    public function wizard()
    {
        $this->assign('title',lang('admin/template/wizard/title'));
        return $this->fetch('admin@template/wizard');
    }

}
