<?php

App::uses('File', 'Utility');
App::uses('Folder', 'Utility');

class ImageHandlerComponent extends Component {

    private $__errors = array();
    
    private $__domainError = 'image_handler';
    
    private $__controller;
    
    private $__filename;
    
    public $exif_data;
    
    /**
     * Hold all format you'd like to use
     * 
     * format['name'] => array(
     *                'path' => defaultPath
     *                'rate' => percent | pixel
     *                'height' => height
     *                'width' => width
     * )
     * 
     * @var array
     */
    public $format;
    

    public function initialize(Controller $controller){
        $this->__controller = $controller;
    }
    
    public function startup($controller) {
    }

    public function beforeFilter() {
        
    }

    public function beforeRender() {
        $this->__controller->set('ImageHandlerFormat', $this->format);
    }

    public function beforeRedirect() {
        
    }

    public function shutdown() {
        
    }
    
    /**
     * 
     * 
     * @param array $format
     * @throws ConfigureException
     */
    private function __checkFormatDefined($format){
        if(!isset($this->format[$format])){
            throw new ConfigureException(__d($this->__domainError, "This format doesn't exists"));
        }
    }
    
    /**
     * 
     * 
     * @param type $format
     * @return type
     */
    private function __getFormatPath($format){
        return $this->format[$format]['path'];
    }
    
    /**
     * 
     * 
     * @param type $type
     * @param type $path
     * @return type
     */
    private function __hasDS($type, $path){
        $d = str_split($path);
        if($type == 'start'){
            return ($d[0] == DS) ? true : false;
        }else{
            return ($d[count($d) - 1] == DS) ? true : false; 
        }
    }
    
    /**
     * 
     * 
     * @param type $dir
     * @param type $optionnalPath
     * @return string The directory, with optionnalPath and a trailing DS at the end
     */
    private function __addAdditionnalPath($dir, $optionnalPath){
        $deleteDS = (($this->__hasDS('end', $dir)) 
                 && $this->__hasDS('start', $optionnalPath));
        $addDS = ((!$this->__hasDS('end', $dir)) 
                 && !$this->__hasDS('start', $optionnalPath));
        if($deleteDS){
            // Delete last DS into $dir and first DS into optionnalPath
            $dir = substr($dir, 0, -1);
            $optionnalPath = substr($optionnalPath, 1);
            $addDS = true;
        }
        // Add optionnalPath
        $dir .= ($addDS) ? DS.$optionnalPath : $optionnalPath;
        return $dir;
    }
    
    /**
     * 
     * 
     * @return type
     * @throws ConfigureException
     */
    private function __getDefaultFormat(){
        $default = false;
        foreach($this->format as $format => $data){
            if(isset($data['default'])){
                $default = $format;
            }
        }
        if($default === false){
            throw new ConfigureException(__d($this->__domainError, "Couldn't find default format"));
        }
        return $default;
    }
    
    /**
     * Add trailing DS if necesserary
     * 
     * @param string $dir
     * @return string
     */
    private function __addTrailingDS($dir){
        // Add / to the end if necessary
        if(false === $this->__hasDS('end', $dir)){
            // Add / to end of $dir
            $dir .= DS;
        }
        return $dir;
    }
    
    /**
     * 
     * @param type $path
     * @todo hasDS('start', $dir): Incorrect for Windows system
     */
    private function __makeAbsolutePath($dir){
        if(false === $this->__hasDS('start', $dir)){
            // Add www_root to $dir
            $dir = WWW_ROOT.$dir;
        }
        return $dir;
    }
    
    
    /**
     * Used to upload one single file to one single format
     * 
     * @param type $data
     * @param mixed $path String or Array. 
     * - String: Must be a format defined
     * - Array: Must contain a format defined as key, optionnal path as value
     * @param type $filename
     * @return type
     * @todo Apply format options onto the uploaded file
     */
    public function upload($file, $path, $filename) {
        $this->__filename = $filename;
        // Get default format
        $format = $path;
        $optionnalPath = '';
        // If path is array
        if(is_array($path)){
            // Get correct format
            $format = key($path);
            $optionnalPath = $path[$format];
        }
        // Check valid format
        $this->__checkFormatDefined($format);
        // Get format's path
        $dir = $this->__getFormatPath($format);
        $dir = $this->__makeAbsolutePath($dir);
        // Add optionnal Path
        $dir = $this->__addAdditionnalPath($dir, $optionnalPath);
        // Add trailing DS
        $dir = $this->__addTrailingDS($dir);
        // Upload
        $upload = false;
        // Try to create directory structure
        if(true === $this->createDir($dir)){
            $this->exif_data = exif_read_data($file['tmp_name']);
            // Try to move uploaded fil to dir
            $upload = (move_uploaded_file($file['tmp_name'], $dir . $this->__filename)) ? true : false;
            if(true === $upload){
                // Apply format options onto the uploaded file
            }
        }
        
        return $upload;
    }
    
    /**
     * Create structure directory
     * 
     * @param type $path
     * @param type $mode
     * @return type
     */
    public function createDir($path, $mode = false){
        $folder = new Folder();
        $create = $folder->create($path, $mode);
        if($create === false){
            throw new ForbiddenException(__d($this->__domainError, "Couldn't create folder"));
        }
        return $create;
    }


    /**
     * This method should be used when wanted to upload one file to ALL format
     * 
     * @param type $file
     * @param type $filename
     */
    public function uploadToAllFormat($file, $filename, $optionalPath){
        // Grab the default format
        $default = $this->__getDefaultFormat();
        // Get the source folder
        $dir = $this->__getFormatPath($default);        
        // Upload the file to the default format
        $upload = $this->upload($file, array($default => $optionalPath), $filename);
        // If upload succeed
        if($upload === true){
//            echo 'upload succeed <br>';
            // Unset the default format to avoid double copy
            $formats = $this->format;
            unset($formats[$default]);
            // loop through all format
            foreach($formats as $format => $data){
                // copy to format
                $this->copy($filename, array($format => $optionalPath));
            }
        }
    }
    
    /**
     * 
     * @param type $format
     * @param type $type height or width
     * @param type $source_size original size
     */
    private function __calculateNewSizeByFormat($format,$type, $source_size){
        $size = $this->format[$format]['size'];
        $value = $this->format[$format][$type];
//        echo "size ".$size."<br>";
//        echo "value ".$value."<br>";
        switch($size){
            case 'percent':
                $newValue = ($value / 100) * $source_size;
                break;
            case 'pixel':
                $newValue = $value;
                break;
            default:
                $newValue = 0;
        }
        return $newValue;
    }
    
    /**
     * Copy the current filename to a destination directorym according to
     * the format and its options defined
     * 
     * @param mixed $path String or Array. 
     * - String: Must be a format defined
     * - Array: Must contain a format defined as key, optionnal path as value
     * 
     * @param type $dir
     * @param type $source
     * @param type $filename Name of the file
     * @param type $newWidth
     * @param type $newHeight
     */
    public function copy($filename, $to) {
        $this->__filename = $filename;
        // Get default format
        $format = $to;
        $optionnalPath = '';
        // If $to is array
        if(is_array($to)){
            // Get correct format
            $format = key($to);
            $optionnalPath = $to[$format];
        }
        // Get default structure directory
        $to = $this->__getFormatPath($format);
        // Add Additionnal path
        $to = $this->__addAdditionnalPath($to, $optionnalPath);
        $source = $this->__getFormatPath($this->__getDefaultFormat());
        $source = $this->__addAdditionnalPath($source, $optionnalPath);
        // Make absolute path
        $to = $this->__makeAbsolutePath($to);
        $source = $this->__makeAbsolutePath($source);
        // Source should be the default folder, so don't need it as parameter
        
        // $dir should be only the format name we want to use for.
        
        //

        
        // Create $to directory if doesn't exists
        if(true === $this->createDir($to)){
            $to .= $filename;
            $source .= $filename;
//            echo "to ".$to."<br>";
//            echo "source ".$source."<br>";
            if (!file_exists($to)) {

                list($width, $height) = getimagesize($source);
                $newHeight = $this->__calculateNewSizeByFormat($format, 'height', $height);
                $newWidth = $this->__calculateNewSizeByFormat($format, 'width', $width);
                $convertPath = $this->__convertPath();

                if ($width > $height) {
                    $cmd = $convertPath.' -resize x' . $newHeight . ' -extent ' . $newWidth . 'x' . $newHeight . ' "' . $source . '" "' . $to . '" ';
                } elseif ($width < $height) {
                    $cmd = $convertPath.' -resize ' . $newWidth . 'x -extent ' . $newWidth . 'x' . $newHeight . ' "' . $source . '" "' . $to . '"';
                } else {
                    $cmd = $convertPath.' -resize ' . $newWidth . 'x' . $newHeight . ' -extent ' . $newWidth . 'x' . $newHeight . ' "' . $source . '" "' . $to . '"';
                }
//                echo $cmd;
//                exit();
                exec($cmd);
            }
        }
        
        // On cree l'image uniquement si elle n'existe pas déjà
        
    }

    public function crop($dir, $source, $filename, $newWidth, $newHeight) {

        $directory = WWW_ROOT . 'attachments' . DS . $dir;
        $sourceFile = WWW_ROOT . 'attachments' . DS . $source;

        if (!is_dir($directory))
            mkdir($directory, 0755, true);

        // On cree l'image uniquement si elle n'existe pas déjà
        if (!file_exists($directory . DS . $filename)) {
            $cmd = '/usr/bin/convert -gravity center -extent ' . $newWidth . 'x' . $newHeight . ' "' . $sourceFile . '" "' . $directory . DS . $filename . '"';
            exec($cmd);
        }
    }
    
    /**
     * 
     * @return string
     */
    private function __convertPath(){
        switch(Configure::read('App.environment')){
            case 'dev':
                return '/opt/local/bin/convert';
                break;
            case 'preprod':
                return '/usr/bin/convert';
                break;
            case 'production':
                return '/usr/bin/convert';
                break;
        }
    }

}