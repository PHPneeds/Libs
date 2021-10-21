<?php declare( strict_types=1 );

/*
 * This file is part of PHPneeds.
 *
 * (c) Mertcan Ayhan <mertowitch@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phpneeds\Libs
{

    use Imagick;
    use ImagickDraw;
    use ImagickPixel;
    use ImagickException;
    use ImagickDrawException;

    /**
     *
     */
    class Image
    {
        private static object $config;

        private Imagick $imagick;

        private string $originFilename;
        private string $cacheFilename;

        private array $originInfo;
        private array $cacheInfo;

        private int $newWidth = 200;
        private int $newHeight = 200;
        private bool $newCrop = false;
        private int $newQuality = 80;
        private bool $newWatermark = false;

        private bool $cached = false;

        public function __construct()
        {
            self::_getConfig();

            $this->imagick = new Imagick();
        }

        private static function _getConfig(): void
        {
            self::$config = include( __DIR__ . '/../../../../confs/conf.image.php' );
        }

        /**
         * @param string $originFilename
         *
         * @return $this
         */
        public function setOrigin( string $originFilename ): Image
        {
            $this->originFilename = $originFilename;

            return $this;
        }

        /**
         * @param int $width
         *
         * @return $this
         */
        public function setWidth( int $width ): Image
        {
            $this->newWidth = $width;

            return $this;
        }

        /**
         * @param int $height
         *
         * @return $this
         */
        public function setHeight( int $height ): Image
        {
            $this->newHeight = $height;

            return $this;
        }

        /**
         * @return $this
         */
        public function setCrop(): Image
        {
            $this->newCrop = true;

            return $this;
        }

        /**
         * @param int $quality
         *
         * @return $this
         */
        public function setQuality( int $quality ): Image
        {
            $this->newQuality = $quality;

            return $this;
        }

        /**
         * @return $this
         */
        public function setwatermark(): Image
        {
            $this->newWatermark = true;

            return $this;
        }

        /**
         * @return $this
         * @throws ImagickException
         */
        public function resize(): Image
        {
            if ( $this->_isCached() )
            {
                return $this;
            }

            try
            {
                $this->_loadOriginImage();
            }
            catch ( \ImagickException $e )
            {
                throw new $e;
            }

            if ( $this->originInfo['width'] < $this->newWidth )
            {
                $width = $this->originInfo['width'];
            } else
            {
                $width = $this->newWidth;
            }

            if ( $this->originInfo['height'] < $this->newHeight )
            {
                $height = $this->originInfo['height'];
            } else
            {
                $height = $this->newHeight;
            }

            try
            {
                $this->imagick->setImageCompressionQuality( $this->newQuality );

                if ( $this->newCrop )
                {
                    $this->imagick->cropThumbnailImage( $width, $height );
                } else
                {
                    $this->imagick->thumbnailImage( $width, $height );
                }
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }

            return $this;
        }

        /**
         * @return bool
         */
        private function _isCached(): bool
        {
            if ( $this->cached === false )
            {
                if ( $this->cached = file_exists( self::$config->PATH['CACHE'] . $this->_generateCacheFilename() . '.jpg' ) )
                {
                    $this->cacheInfo = $this->_getCacheInfo();
                }
            }

            return $this->cached;
        }

        /**
         * @return string
         */
        private function _generateCacheFilename(): string
        {
            $cacheFilename = array();

            $cacheFilename[] = $this->originFilename;
            $cacheFilename[] = $this->newWidth . 'x' . $this->newHeight;
            $cacheFilename[] = $this->newQuality;
            $cacheFilename[] = ( $this->newCrop ) ? 'croped' : 'nocrop';

            if ( $this->newWatermark )
            {
                $cacheFilename[] = 'watermark-' . self::$config->WATERMARK['TEXT1'] . self::$config->WATERMARK['TEXT2'];
            }

            return $this->cacheFilename = implode( '-', $cacheFilename );
        }

        /**
         * @return array
         */
        private function _getCacheInfo(): array
        {
            $imageSize = getimagesize( self::$config->PATH['CACHE'] . $this->cacheFilename . '.jpg' );
            $fileExt   = explode( '.', $this->cacheFilename . '.jpg' );

            return array(
                'filename'     => explode( '.', basename( $this->cacheFilename ) )[0],
                'width'        => $imageSize[0],
                'height'       => $imageSize[1],
                'mime'         => 'image/jpeg',
                'extension'    => 'jpg',
                'modifiedDate' => filemtime( self::$config->PATH['CACHE'] . $this->cacheFilename . '.jpg' ),
                'fileRealPath' => self::$config->PATH['CACHE'] . $this->cacheFilename . '.jpg'
            );
        }

        /**
         * @throws ImagickException
         */
        private function _loadOriginImage(): void
        {
            try
            {
                $this->imagick->readImage( self::$config->PATH['ORIGIN'] . $this->originFilename . '.jpg' );
                $this->originInfo = $this->_getOriginInfo();
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }
        }

        /**
         * @return array
         */
        private function _getOriginInfo(): array
        {
            $imageSize = getimagesize( self::$config->PATH['ORIGIN'] . $this->originFilename . '.jpg' );
            $fileExt   = explode( '.', $this->originFilename . '.jpg' );

            return array(
                'filename'     => explode( '.', basename( $this->originFilename ) )[0],
                'width'        => $imageSize[0],
                'height'       => $imageSize[1],
                'mime'         => $imageSize['mime'],
                'extension'    => end( $fileExt ),
                'modifiedDate' => filemtime( self::$config->PATH['ORIGIN'] . $this->originFilename . '.jpg' ),
                'fileRealPath' => self::$config->PATH['ORIGIN'] . $this->originFilename . '.jpg'
            );
        }

        /**
         * @return string
         * @throws ImagickDrawException
         * @throws ImagickException
         */
        public function getBlob(): string
        {
            try
            {
                if ( $this->_isCached() )
                {
                    $this->_getCachedBlob();
                } else
                {
                    if ( $this->newWatermark )
                    {
                        $this->_addWatermark();
                    }

                    $this->_cacheThis();
                }

                return $this->imagick->getImageBlob();
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }
        }

        /**
         * @throws ImagickException
         */
        private function _getCachedBlob(): void
        {
            try
            {
                $this->imagick->readImage( $this->cacheInfo['fileRealPath'] );
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }
        }

        /**
         * @return bool
         * @throws ImagickDrawException
         * @throws ImagickException
         */
        private function _addWatermark(): bool
        {
            try
            {
                $imagick = new Imagick;

                $imagick->newImage( 170, 120, new ImagickPixel( 'none' ) );

                $imagickDraw = new ImagickDraw;
                $imagickDraw->setFillColor( self::$config->WATERMARK['FONT']['COLOR'] );
                $imagickDraw->setFillOpacity( self::$config->WATERMARK['OPACITY'] );
                $imagickDraw->setFontSize( self::$config->WATERMARK['FONT']['SIZE'] );
                $imagickDraw->setGravity( Imagick::GRAVITY_NORTHWEST );

                $imagick->annotateImage( $imagickDraw, 15, 25, 45, self::$config->WATERMARK['TEXT1'] );
                $imagick->annotateImage( $imagickDraw, 70, 120, - 45, self::$config->WATERMARK['TEXT2'] );

                for ( $width = 0; $width < $this->imagick->getImageWidth(); $width += 150 )
                {
                    for ( $height = 0; $height < $this->imagick->getImageHeight(); $height += 100 )
                    {
                        $this->imagick->compositeImage( $imagick, Imagick::COMPOSITE_OVER, $width, $height );
                    }
                }
            }
            catch ( ImagickException  $e )
            {
                throw new $e;
            }
            catch ( ImagickDrawException  $d )
            {
                throw new $d;
            }

            unset( $imagick, $imagickDraw, $width, $height );

            return true;
        }

        /**
         * @throws ImagickException
         */
        private function _cacheThis(): void
        {
            try
            {
                $this->imagick->writeImage( self::$config->PATH['CACHE'] . $this->cacheFilename . '.jpg' );
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }
        }

        /**
         * @return array
         */
        public function getFileInfo(): array
        {
            if ( $this->_isCached() )
            {
                return $this->cacheInfo;
            }

            return $this->originInfo;
        }

    }
}
