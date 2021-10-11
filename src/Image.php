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

    class Image
    {
        private static object $config;
        private string $sourceFile;
        private string $cacheFilename;
        private Imagick $imagick;
        private array $sourceInfo;
        private array $cacheInfo;
        private int $newWidth = 200;
        private int $newHeight = 200;
        private bool $newCrop = false;
        private int $newQuality = 80;
        private bool $isWatermark = false;
        private bool $isCached = false;

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
         * @param string $sourceFile
         *
         * @return $this
         * @throws ImagickException
         */
        public function pick( string $sourceFile ): Image
        {
            $this->_reset();

            $this->sourceFile = $sourceFile;

            try
            {
                $this->imagick->readImage( self::$config->PATH['ORIGIN'] . $sourceFile );
                $this->sourceInfo = $this->_getInfo();
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }

            return $this;
        }

        private function _reset(): void
        {
            $this->cacheFilename = '';
            $this->sourceInfo    = array();
            $this->newWidth      = 200;
            $this->newHeight     = 200;
            $this->newCrop       = false;
            $this->newQuality    = 80;
            $this->isWatermark   = false;
            $this->isCached      = false;
        }

        /**
         * @return array
         */
        private function _getInfo(): array
        {
            $imageSize = getimagesize( self::$config->PATH['ORIGIN'] . $this->sourceFile );
            $fileExt   = explode( '.', $this->sourceFile );

            return array(
                'filename'     => explode( '.', basename( $this->sourceFile ) )[0],
                'width'        => $imageSize[0],
                'height'       => $imageSize[1],
                'mime'         => $imageSize['mime'],
                'extension'    => end( $fileExt ),
                'modifiedDate' => filemtime( self::$config->PATH['ORIGIN'] . $this->sourceFile )
            );
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
         * @return $this
         * @throws ImagickException
         */
        public function resize(): Image
        {

            if ( $this->isCached() )
            {
                return $this;
            }

            if ( $this->sourceInfo['width'] < $this->newWidth )
            {
                $width = $this->sourceInfo['width'];
            } else
            {
                $width = $this->newWidth;
            }

            if ( $this->sourceInfo['height'] < $this->newHeight )
            {
                $height = $this->sourceInfo['height'];
            } else
            {
                $height = $this->newHeight;
            }

            try
            {
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
        private function isCached(): bool
        {
            if ( $this->isCached === false )
            {
                return $this->isCached = file_exists( self::$config->PATH['CACHE'] . $this->_generateCacheFilename() );
            }

            return $this->isCached;
        }

        /**
         * @return string
         */
        private function _generateCacheFilename(): string
        {
            $cacheFilename = array();

            $cacheFilename[] = $this->sourceInfo['filename'];
            $cacheFilename[] = $this->newWidth . 'x' . $this->newHeight;
            $cacheFilename[] = $this->newQuality;
            $cacheFilename[] = ( $this->newCrop ) ? 'croped' : 'nocrop';

            if ( $this->isWatermark )
            {
                $cacheFilename[] = 'watermark-' . self::$config->WATERMARK['TEXT1'] . self::$config->WATERMARK['TEXT2'];
            }

            return $this->cacheFilename = implode( '-', $cacheFilename ) . '.jpg';
        }

        /**
         * @return $this
         * @throws ImagickDrawException
         * @throws ImagickException
         */
        public function addWatermark(): Image
        {
            $this->isWatermark = true;

            if ( $this->isCached() )
            {
                return $this;
            }

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

            return $this;
        }

        /**
         * @param int $quality
         *
         * @return $this
         * @throws ImagickException
         */
        public function setQuality( int $quality ): Image
        {
            $this->newQuality = $quality;

            $this->imagick->setImageCompressionQuality( $this->newQuality );

            return $this;
        }

        /**
         * @return string
         * @throws ImagickException
         */
        public function getBlob(): string
        {
            try
            {
                if ( $this->isCached() )
                {
                    $this->_getCachedBlob();
                } else
                {
                    $this->_cacheThis();
                }

                $this->cacheInfo = array(
                    'filename'     => explode( '.', basename( $this->cacheFilename ) )[0],
                    'width'        => $this->newWidth,
                    'height'       => $this->newHeight,
                    'mime'         => 'image/jpeg',
                    'extension'    => 'jpg',
                    'modifiedDate' => filemtime( self::$config->PATH['CACHE'] . $this->cacheFilename ),
                    'fileRealPath' => self::$config->PATH['CACHE'] . $this->cacheFilename
                );

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
                $this->imagick->readImage( self::$config->PATH['CACHE'] . $this->cacheFilename );
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }
        }

        /**
         * @throws ImagickException
         */
        private function _cacheThis(): void
        {
            try
            {
                $this->imagick->writeImage( self::$config->PATH['CACHE'] . $this->cacheFilename );
            }
            catch ( ImagickException $e )
            {
                throw new $e;
            }
        }

        public function getCachedInfo(): array
        {
            if ( $this->isCached() )
            {
                return $this->cacheInfo;
            }

            return $this->sourceInfo;
        }

    }
}
