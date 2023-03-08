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

		$filename = $event->arguments(0);
		/* @var InputfieldImage $inputfield */
		$inputfield = $event->object;
		// Only for InputfieldImage
		if(!$inputfield instanceof InputfieldImage) return;

		// Return early if image does not have webp extension
		$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
		if($ext !== 'webp') return;

		$pageimages = $inputfield->value;
		$file = $pageimages->path() . $filename;
		$dirpath = $pageimages->path();
		$path_parts = pathinfo($file);

		// Basename for converted image
		$basename = $path_parts['filename'] . '.jpg';
		// Adjust basename if it will clash with an existing file
		$i = 1;
		while(is_file($dirpath . $basename)) {
			$basename = "{$path_parts['filename']}-$i.jpg";
			$i++;
		}
		$filepath = $dirpath . $basename;

		// Use ImageMagick if installed, otherwise GD
		if(extension_loaded('imagick')) {
			$image = new \Imagick($file);

			// Not sure if the following are needed but just in case
			$image->setImageBackgroundColor('white');
			$image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN); // Flatten layers if present
			$image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE); // Avoid transparent areas becoming black

			$image->setImageCompression(\Imagick::COMPRESSION_JPEG);
			$image->setImageCompressionQuality($this->quality);
			$image->setImageFormat('jpg');
			$image->writeImage($filepath);

		} else {

			if(!function_exists('imagecreatefromwebp')) return;
			$image = imagecreatefromwebp($file);
			imagejpeg($image, $filepath, $this->quality);
			imagedestroy($image);

		}

		// Delete original
		unlink($file);

		$event->arguments(0, $filepath);
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
