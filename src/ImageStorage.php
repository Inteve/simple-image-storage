<?php

	namespace Inteve\SimpleImageStorage;

	use Nette;
	use Nette\Http\FileUpload;
	use Nette\Utils\FileSystem;
	use Nette\Utils\Image;
	use Nette\Utils\Random;
	use Nette\Utils\Strings;


	class ImageStorage extends Nette\Object
	{
		/** only shrinks images */
		const SHRINK_ONLY = 1;

		/** will ignore aspect ratio */
		const STRETCH = 2;

		/** fits in given area so its dimensions are less than or equal to the required dimensions */
		const FIT = 0;

		/** fills given area so its dimensions are greater than or equal to the required dimensions */
		const FILL = 4;

		/** fills given area exactly */
		const EXACT = 8;

		/** @var string */
		private $directory;

		/** @var string */
		private $publicDirectory;

		/** @var string|NULL */
		private $storageName;


		public function __construct($directory, $publicDirectory, $storageName = NULL)
		{
			$this->directory = $this->normalizePath($directory, $storageName);
			$this->publicDirectory = $this->normalizePath($publicDirectory, $storageName);
			$this->storageName = $storageName;
		}


		/**
		 * @param  FileUpload
		 * @param  string|NULL
		 * @param  array|string|NULL
		 * @return string  filepath (namespace/file.ext)
		 * @throws ImageStorageException
		 */
		public function upload(FileUpload $image, $namespace = NULL, $mimeTypes = NULL)
		{
			if (!$image->isOk()) {
				throw new ImageStorageException('File is broken');
			}

			if (!$image->isImage()) {
				$contentType = $image->getContentType();
				$isValid = FALSE;

				if (isset($mimeTypes)) {
					$mimeTypes = is_array($mimeTypes) ? $mimeTypes : explode(',', $mimeTypes);
					$isValid = in_array($contentType, $mimeTypes, TRUE);
				}

				if (!$isValid) {
					throw new ImageStorageException('File must be image, ' . $contentType . ' given');
				}
			}

			$name = NULL;
			$path = NULL;
			$file = NULL;
			$sanitizedName = $image->getSanitizedName();
			$ext = pathinfo($sanitizedName, PATHINFO_EXTENSION);
			$sanitizedName = pathinfo($sanitizedName, PATHINFO_FILENAME) . '.' . Strings::lower($ext);

			do {
				$name = Random::generate(10) . '.' . $sanitizedName;
				$file = $this->formatFilePath($name, $namespace);
				$path = $this->getPath($file);

			} while (file_exists($path));

			$image->move($path);
			return $file;
		}


		/**
		 * @param  Image
		 * @param  int
		 * @param  int|NULL (for JPEG, PNG)
		 * @param  string|NULL
		 * @return string  filepath (namespace/file.ext)
		 * @throws ImageStorageException
		 */
		public function save(Image $image, $format, $quality = NULL, $namespace = NULL)
		{
			$ext = NULL;

			if ($format === Image::JPEG) {
				$ext = 'jpg';

			} elseif ($format === Image::PNG) {
				$ext = 'png';

			} elseif ($format === Image::GIF) {
				$ext = 'gif';

			} else {
				throw new ImageStorageException("Unknow format '$format'.");
			}

			do {
				$name = Random::generate(10) . '.' . Random::generate(5) . '.' . $ext;
				$file = $this->formatFilePath($name, $namespace);
				$path = $this->getPath($file);

			} while (file_exists($path));

			@mkdir(dirname($path), 0777, TRUE); // @ - adresar muze existovat
			$image->save($path, $quality, $format);
			return $file;
		}


		/**
		 * @param  string  filepath (namespace/file.ext)
		 * @return void
		 */
		public function delete($file)
		{
			FileSystem::delete($this->getPath($file));
			FileSystem::delete($this->directory . '/' . $this->formatThumbnailPath($file, NULL));
		}


		/**
		 * @param  string  filepath (namespace/file.ext)
		 * @return string
		 */
		public function getPath($file)
		{
			return $this->directory . '/' . $this->formatOriginalPath($file);
		}


		/**
		 * @param  string  filepath (namespace/file.ext)
		 * @return string
		 */
		public function getPublicPath($file)
		{
			return $this->publicDirectory . '/' . $this->formatOriginalPath($file);
		}


		/**
		 * @param  string  filepath (namespace/file.ext)
		 * @param  int|NULL
		 * @param  int|NULL
		 * @param  int|NULL
		 * @param  int|NULL
		 * @return string
		 */
		public function thumbnail($file, $width, $height, $flags = NULL, $quality = NULL)
		{
			$thumbnail = $this->prepareThumbnail($file, $width, $height, $flags, $quality);
			$path = $this->formatThumbnailPath($file, $thumbnail);

			$thumbnailPath = $this->directory . '/' . $path;

			if (!file_exists($thumbnailPath)) {
				$originalPath = $this->getPath($file);
				$this->generateThumbnail($originalPath, $thumbnailPath, $thumbnail);
			}

			return $this->publicDirectory . '/' . $path;
		}


		/**
		 * @param  string
		 * @param  string
		 * @param  array  thumbnailData
		 * @return void
		 */
		private function generateThumbnail($sourceImage, $outputImage, array $thumbnail)
		{
			if (!isset($thumbnail['width']) && !isset($thumbnail['height'])) {
				throw new ImageStorageException('Width & height missing');
			}

			$image = Image::fromFile($sourceImage);
			$image->resize($thumbnail['width'], $thumbnail['height'], $thumbnail['flags']);

			FileSystem::createDir(dirname($outputImage));
			$image->save($outputImage, $thumbnail['quality']);
		}


		/**
		 * @param  string  filepath (namespace/file.ext)
		 * @param  int|NULL
		 * @param  int|NULL
		 * @param  int|NULL
		 * @param  int|NULL
		 * @return array  thumbnail data
		 */
		private function prepareThumbnail($file, $width, $height, $flags = NULL, $quality = NULL)
		{
			if (!isset($width) && !isset($height)) {
				throw new ImageStorageException('Width & height missing');
			}

			$flags = (int) $flags;

			if ($flags <= 0) {
				$flags = 0; // fit

			} elseif ($flags > 8) {
				$flags = 8; // exact
			}

			return array(
				'width' => ($width > 0) ? ((int) $width) : NULL,
				'height' => ($height > 0) ? ((int) $height) : NULL,
				'quality' => ($quality > 0) ? ((int) $quality) : NULL,
				'flags' => $flags,
			);
		}


		/**
		 * Sestavi cestu k originalnimu obrazku
		 * @param  string  filepath (namespace/file.ext)
		 * @return string
		 */
		private function formatOriginalPath($file)
		{
			$data = $this->parseFilePath($file);
			return $data['namespace'] . 'o/' . $data['delimiter'] . '/' . $data['basename'];
		}


		/**
		 * Sestavi cestu do slozky nahledu
		 * @param  string  filepath (namespace/file.ext)
		 * @return string
		 */
		private function formatThumbnailPath($file, array $parameters = NULL)
		{
			$data = $this->parseFilePath($file);
			$directory = $data['namespace'] . 't/' . $data['delimiter'] . '/' . $data['filename'];

			if ($parameters === NULL) {
				return $directory;
			}

			$parts = array(
				$data['filename'],
				isset($parameters['width']) ? $parameters['width'] : 0,
				isset($parameters['height']) ? $parameters['height'] : 0,
				isset($parameters['quality']) ? $parameters['quality'] : 'n',
				isset($parameters['flags']) ? $parameters['flags'] : 0,
			);

			return $directory . '/' . implode('_', $parts) . '.' . $data['ext'];
		}


		/**
		 * @param  string  filepath (namespace/file.ext)
		 * @return array  [directory => (string), basename => (string), filename => (string), ext => (string)]
		 */
		private function parseFilePath($file)
		{
			$file = trim($file);

			if ($file === '') {
				throw new ImageStorageException('Missing filepath');
			}

			$info = pathinfo($file);
			$basename = $info['basename'];
			return array(
				'namespace' => $info['dirname'] !== '.' ? ($info['dirname'] . '/') : NULL,
				'delimiter' => substr($basename . '00', 0, 2) . '/' . substr($basename . '00', 2, 2),
				'basename' => $info['basename'],
				'filename' => $info['filename'],
				'ext' => isset($info['extension']) ? $info['extension'] : NULL,
			);
		}


		/**
		 * Sestavi "filepath" (namespace/file.ext)
		 * @param  string
		 * @param  string|NULL
		 * @return string
		 */
		private function formatFilePath($file, $namespace = NULL)
		{
			return ltrim(trim($namespace, '/') . '/' . trim($file, '/'), '/');
		}


		private function normalizePath($directory, $suffix)
		{
			return rtrim(rtrim($directory, '/') . '/' . $suffix, '/');
		}
	}


	class ImageStorageException extends \RuntimeException
	{
	}
