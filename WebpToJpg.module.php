<?php namespace ProcessWire;

class WebpToJpg extends WireData implements Module, ConfigurableModule {

	protected $envFailMessage;

	/**
	 * Construct
	 */
	public function __construct() {
		parent::__construct();
		$this->envFailMessage = $this->_('You cannot use the WebpToJpg module because your environment has neither the Imagick extension nor the GD imagecreatefromwebp() function.');
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

		// Get converter and throw exception if environment fails
		$converter = $this->getConverter();
		if(!$converter) throw new WireException($this->envFailMessage);

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

		// Convert image to JPG using selected or available converter
		if($converter === 'imagick') {
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

			$image = imagecreatefromwebp($filename);
			imagejpeg($image, $new_filename, $this->quality);
			imagedestroy($image);

		}

		// Delete original
		unlink($filename);

		return $new_filename;
	}

	/**
	 * Get the selected or available converter software
	 */
	protected function getConverter() {
		$converter = $this->converter;
		if(!$converter) $converter = 'imagick';
		if($converter === 'imagick' && !extension_loaded('imagick')) {
			$converter = 'gd';
		}
		if($converter === 'gd' && !function_exists('imagecreatefromwebp')) {
			$converter = false;
		}
		return $converter;
	}

	/**
	 * Config inputfields
	 *
	 * @param InputfieldWrapper $inputfields
	 */
	public function getModuleConfigInputfields($inputfields) {
		$modules = $this->wire()->modules;

		$converter = $this->getConverter();
		if(!$converter) $this->wire()->error($this->envFailMessage);

		/** @var InputfieldRadios $f */
		$f = $modules->get('InputfieldRadios');
		$f->name = 'converter';
		$f->label = $this->_('Image converter');
		$f->notes = $this->_('If an option is disabled here it means that the necessary software is not available in your environment.');
		$f->addOption('imagick', 'Imagick', ['disabled' => !extension_loaded('imagick')]);
		$f->addOption('gd', 'GD', ['disabled' => !function_exists('imagecreatefromwebp')]);
		$f->optionColumns = 1;
		if($converter) $f->value = $converter;
		$inputfields->add($f);

		/* @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f_name = 'quality';
		$f->name = $f_name;
		$f->label = $this->_('Quality for JPG conversion');
		$f->inputType = 'number';
		$f->value = $this->$f_name;
		$inputfields->add($f);

	}

}
