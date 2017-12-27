
# Simple Image Storage

Image storage for Nette.


## Installation

[Download a latest package](https://github.com/inteve/simple-image-storage/releases) or use [Composer](http://getcomposer.org/):

```
composer require inteve/simple-image-storage
```

Library requires PHP 5.3.0 or later.


## Usage

``` php
use Inteve\SimpleImageStorage\ImageStorage;
```


### Register in config

``` yaml
parameters:
	imageStorage:
		directory: %wwwDir%
		publicDirectory: @httpRequest::getUrl()::getBaseUrl()
		storageName: images # optional

services:
	- Inteve\SimpleImageStorage\ImageStorage(%imageStorage.directory%, %imageStorage.publicDirectory%, %imageStorage.storageName%)
```


### Store image

``` php
<?php
$image = $imageStorage->upload($fileUpload);
// $image = 'image-name.jpg'

$avatar = $imageStorage->upload($fileUpload, 'upload/avatars');
// $avatar = 'upload/avatars/image-name.jpg';
```


### Delete image

``` php
<?php
$imageStorage->delete('upload/avatar/image-name.jpg');
```


### Get original path

``` php
<?php
$path = $imageStorage->getRealPath('upload/avatar/image-name.jpg');
```


### Get original public path

``` php
<?php
$path = $imageStorage->getPublicPath('upload/avatar/image-name.jpg');
```


### Thumbnails

``` php
<?php
$path = $imageStorage->thumbnail($file, $width, $height, $flags = NULL, $quality = NULL);
$path = $imageStorage->thumbnail('upload/avatar/image-name.jpg', 512, 256);
```

It uses `Nette\Utils\Image` by default but you can provide custom thumbnail generator in constructor:

``` php
$imageStorage = new ImageStorage(..., ..., ..., function ($sourcePath, $outputPath, array $thumbnailData) {
	$im = new Imagick;
	$im->readImage($sourcePath);
	$im->crop(...);
	$im->writeImage($outputPath);
});
```

* `string $sourcePath` - path to original image
* `string $outputPath` - thumbnail path
* `array $thumbnailData`
	* `int|NULL width` - width of thumbnail or NULL
	* `int|NULL height` - height of thumbnail or NULL
	* `int|NULL quality` - quality of output image or NULL
	* `int flags` - see constants ImageStorage::SHRINK_ONLY, STRETCH, FIT, FILL & EXACT


### In template

``` php
class BasePresenter extends Nette\Application\UI\Presenter
{
	/** @var  Inteve\SimpleImageStorage\ImageStorage  @inject */
	public $imageStorage;


	protected function beforeRender()
	{
		parent::beforeRender();
		$this->template->img = $this->imageStorage;
	}
}
```

``` smarty
<img src="{$img->thumbnail($avatar, 512, 256)}">
<img src="{$img->thumbnail($avatar, 512, 256, $img::SHRINK_ONLY)}">
<img src="{$img->thumbnail($avatar, 512, 256, $img::STRETCH)}">
<img src="{$img->thumbnail($avatar, 512, 256, $img::FILL)}">
<img src="{$img->thumbnail($avatar, 512, 256, $img::EXACT)}">
```

------------------------------

License: [New BSD License](license.md)
<br>Author: Jan Pecha, https://www.janpecha.cz/
