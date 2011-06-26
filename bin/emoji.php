<?php

class EmojiCssBuilder
{
	/**
	 * @var array
	 */
	protected $_config = array(
		'width' => 20,
		'height' => 20,
		'alias' => 'default',
		'json' => false,
		'css' => true,
		'result_dir' => '../result',
		'xml_file' => '../assets/emoji.xml',
	);

	/**
	 * @var int
	 */
	protected $_top = 0;

	/**
	 * @var int
	 */
	protected $_left = 0;

	/**
	 * @var SimpleXMLElement
	 */
	protected $_xml = null;

	/**
	 * @var array
	 */
	protected $_cssData = array();

	/**
	 * @param array $config
	 */
	public function __construct($config = array())
	{
		if (!is_array($config)) {
			$config = array($config);
		}
		$this->_config = array_merge($this->_config, $config);

		if ($this->_config['result_dir'] !== false) {
			$this->_config['result_dir'] = realpath($this->_config['result_dir']);
			if (!file_exists($this->_config['result_dir'])) {
				throw new Exception("result_dir not found");
			}
		}

		$this->_config['xml_file'] = realpath($this->_config['xml_file']);
		if (!file_exists($this->_config['xml_file'])) {
			throw new Exception("xml_file not found");
		}

		$this->_config['width'] = (int)$this->_config['width'];
		$this->_config['height'] = (int)$this->_config['height'];
		$this->_config['css'] = (boolean)$this->_config['css'];
		$this->_config['json'] = (boolean)$this->_config['json'];
		$this->_xml = new SimpleXMLElement(file_get_contents($this->_config['xml_file']));
		$this->_top = $this->_config['width'] < $this->_config['height'] ?
			($this->_config['height'] - $this->_config['width']) / 2 : 0;
	}

	/**
	 * @return string
	 */
	protected function _getSprite()
	{
		$resultImage = null;
		foreach ($this->_xml->xpath('//item/@src') as $i => $src) {

			$file = dirname($this->_config['xml_file']) .'/'. (string)$src;
			if (file_exists($file)) {

				$nextResultImage = imagecreatetruecolor(
					$this->_config['width'], $this->_config['height'] * ($i + 1)
				);
				
				imagesavealpha($nextResultImage, true);
				imagefill($nextResultImage, 0, 0, imagecolorallocate($nextResultImage, 255, 255, 255));
				if ($resultImage) {
					imagecopy($nextResultImage, $resultImage, 0, 0, 0, 0,
						$this->_config['width'], $this->_config['height'] * $i
					);
				}
				$resultImage = $nextResultImage;

				$wrapper = imagecreatetruecolor(
					$this->_config['width'], $this->_config['height']
				);
				imagesavealpha($wrapper, true);
				imagefill($wrapper, 0, 0, imagecolorallocate($wrapper, 255, 255, 255));

				$part = imagecreatefrompng($file);
				imagesavealpha($part, true);

				imagecopyresampled($wrapper, $part, $this->_left, $this->_top, 0, 0,
					$this->_config['width'], $this->_config['width'], 20, 20);

				imagecopyresampled($resultImage, $wrapper, 0, $this->_config['height'] * $i, 0, 0,
					$this->_config['width'], $this->_config['height'],
						$this->_config['width'], $this->_config['height']
				);

				if ($this->_config['css']) {
					$this->_cssData[] = '.emoji-'. $this->_config['alias'] .'-'. basename($file, '.png')
						.'{background-position:0 -'. $this->_config['height'] * $i .'px}';
				}
			}

		}

		if (!$resultImage) {
			throw new Exception('Error render sprite');
		}

		ob_start();
		$result = imagepng($resultImage);
		if (!$result) {
			ob_end_flush();
			throw new Exception('Error render sprite');
		}

		return ob_get_clean();
	}

	/**
	 * @throws Exception
	 * @param string  $dir
	 * @param string $filename
	 * @param string $content
	 * @return boolean
	 */
	protected function _save($dir = null, $filename, $content)
	{
		if (!$dir) {
			if (!$this->_config['result_dir']) {
				throw new Exception("result directory is not provided");
			}
			$dir = $this->_config['result_dir'];
		}
		
		return file_put_contents(rtrim($dir, '\/') .'/'. $filename, $content);
	}

	/**
	 * @param string $dir
	 * @return void
	 */
	public function save($dir = null)
	{
		$this->saveSprite($dir);
		$this->saveCss($dir);
		$this->saveJson($dir);
	}

	/**
	 * @return string
	 */
	public function getSprite()
	{
		return $this->_getSprite();
	}

	/**
	 * @param string $dir
	 * @return boolean
	 */
	public function saveSprite($dir = null)
	{
		return $this->_save($dir,
			'emoji-'. $this->_config['alias'] .'.png',
				$this->getSprite()
		);
	}

	/**
	 * @return string
	 */
	public function getCss()
	{
		if ($this->_config['css']) {
			$css = '.emoji-'. $this->_config['alias'] .'{display:inline-block;'
				.'width:'. $this->_config['width'] .'px;height:'. $this->_config['height'] .'px;'
				.'vertical-align:middle;background:transparent url(emoji-'. $this->_config['alias'] .'.png)no-repeat;//display:inline}';
			$css .= implode('', $this->_cssData);
			return $css;
		}
		return '';
	}

	/**
	 * @param string $dir
	 * @return boolean
	 */
	public function saveCss($dir = null)
	{
		if (!$this->_config['css']) {
			return true;
		}

		return $this->_save($dir,
			'emoji-'. $this->_config['alias'] .'.css',
				$this->getCss()
		);
	}

	/**
	 * @return string
	 */
	public function getJson()
	{
		if ($this->_config['css']) {
			if ($this->_config['json']) {
				$js = array();
				foreach ($this->_xml->xpath('//group') as $group) {
					$data = array();
					foreach($group->xpath('.//item/@code') as $code) {
						$data[] = (string)$code;
					}
					$js[(string)$group->attributes()->id] = $data;
				}
				return json_encode($js);
			}
		}
		return '';
	}

	/**
	 * @param string $dir
	 * @return boolean
	 */
	public function saveJson($dir = null)
	{
		if (!$this->_config['json']) {
			return true;
		}

		return $this->_save($dir,
			'emoji.json',
				$this->getJson()
		);
	}
}

if (PHP_SAPI == 'cli') {

	$config = array(
		'width' => 20,
		'height' => 20,
		'alias' => 'default',
		'json' => false,
		'css' => true,
		'result_dir' => '../result',
		'xml_file' => '../assets/emoji.xml',
	);
	
	if (!empty($argv)) {
		foreach ($argv as $str) {
			if (strpos($str, '--') === 0 && strpos($str, '=') !== false) {
				$str = explode('=', substr($str, 2), 2);
				if (isset($config[$str[0]])) {
					$config[$str[0]] = $str[1];
				}
			}
		}
	}
	
	$builder = new EmojiCssBuilder($config);

	$builder->save();

}