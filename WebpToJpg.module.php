<?php namespace ProcessWire;

class WebpToJpg extends WireData implements Module, ConfigurableModule {

	/**
	 * Construct
	 */
	public function __construct() {
		parent::__construct();
		$this->quality = 100;
	}

	/**
	 * Ready
	 */
	public function ready() {
		$this->addHookBefore('InputfieldFile::processInputAddFile', $this, 'beforeImageAdded');
	}

	/**
	 * Before InputfieldFile::processInputAddFile
	 *
	 * @param HookEvent $event
	 */
	protected function beforeImageAdded(HookEvent $event) {

		$basename = $event->arguments(0);
		/* @var InputfieldImage $inputfield */
		$inputfield = $event->object;
		// Only for InputfieldImage
		if(!$inputfield instanceof InputfieldImage) return;

		// Return early if image does not have webp extension
		$ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
		if($ext !== 'webp') return;

		$pageimages = $inputfield->value;
		$filename = $pageimages->path() . $basename;

		$event->arguments(0, $this->convertToWebp($filename));
	}

	/**
	 * Convert the supplied image to WebP format
	 *
	 * @param string $filename
	 * @return string
	 */
	public function convertToWebp($filename) {

		$path_parts = pathinfo($filename);
		$dirname = $path_parts['dirname'] . '/';

		// Basename for converted image
		$basename = $path_parts['filename'] . '.jpg';
		// Adjust basename if it will clash with an existing file
		$i = 1;
		while(is_file($dirname . $basename)) {
			$basename = "{$path_parts['filename']}-$i.jpg";
			$i++;
		}
		$new_filename = $dirname . $basename;

		// Use ImageMagick if installed, otherwise GD
		if(extension_loaded('imagick')) {
			$image = new \Imagick($filename);

			// Not sure if the following are needed but just in case
			$image->setImageBackgroundColor('white');
			$image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN); // Flatten layers if present
			$image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE); // Avoid transparent areas becoming black

			$image->setImageCompression(\Imagick::COMPRESSION_JPEG);
			$image->setImageCompressionQuality($this->quality);
			$image->setImageFormat('jpg');
			$image->writeImage($new_filename);

		} else {

			if(!function_exists('imagecreatefromwebp')) return $filename;
			$image = imagecreatefromwebp($filename);
			imagejpeg($image, $new_filename, $this->quality);
			imagedestroy($image);

		}

		// Delete original
		unlink($filename);

		return $new_filename;
	}

	/**
	 * Config inputfields
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields($inputfields) {

		/* @var InputfieldInteger $f */
		$f = $this->wire()->modules->get('InputfieldInteger');
		$f_name = 'quality';
		$f->name = $f_name;
		$f->label = $this->_('Quality for JPG conversion');
		$f->inputType = 'number';
		$f->value = $this->$f_name;
		$inputfields->add($f);

	}

}
