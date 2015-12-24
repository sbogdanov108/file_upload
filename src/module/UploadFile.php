<?php

/**
 * Created by PhpStorm.
 * User: sergey
 * Date: 24.10.15
 * Time: 17:03
 */

namespace module;

/**
 * 1. Ограничение размера загружаемого файла
 * 2. Проверка MIME-type и переименование раширений подозрительных файлов
 * 3. Загрузка одного или нескольких файлов
 * 4. Настройка поведения класса через передаваемые параметры
 * 5. Автоматическое переименование одинаковых имён загружаемых файлов
 * 6. Использование пространства имён для избежания конфликта имён
 *
 * Class UploadFile
 * @package foundationphp
 */
class UploadFile
{
  protected $destination;
  protected $messages = [];
  protected $maxSize = 51200;
  protected $permittedTypes = [
    'image/jpeg',
    'image/pjpeg',
    'image/gif',
    'image/png',
    'image/webp'
  ];
  protected $newName;
  protected $typeCheckingOn = true;
  protected $notTrusted = [ 'bin', 'cgi', 'exe', 'js', 'pl', 'php', 'py', 'sh' ];
  protected $suffix = '.upload';
  protected $renameDuplicates;

  public function __construct( $uploadFolder )
  {
    if( ! is_dir( $uploadFolder ) || ! is_writable( $uploadFolder ) )
      throw new \Exception( "$uploadFolder must be a valid, writable folder." );

    if( $uploadFolder[ strlen( $uploadFolder ) - 1 ] != '/' )
      $uploadFolder .= '/';

    $this->destination = $uploadFolder;
  }

  /**
   * Установить максимальный размер загружаемого файла
   * @param $bytes - макс. размер файла в байтах
   *
   * @throws \Exception
   */
  public function setMaxSize( $bytes )
  {
    $serverMax = self::convertToBytes( ini_get( 'upload_max_filesize' ) );

    if( $bytes > $serverMax )
      throw new \Exception( 'Maximum size cannot exceed server limit for individual files: ' . self::convertFromBytes( $bytes ) );

    if( is_numeric( $bytes ) && $bytes > 0 )
      $this->maxSize = $bytes;
  }

  /**
   * Конвертирование значения upload_max_filesize в байты
   * @param $val
   *
   * @return int|string
   */
  public static function convertToBytes( $val )
  {
    $val = trim( $val );
    $last = strtolower( $val[ strlen( $val ) - 1 ] );

    if ( in_array( $last, array( 'g', 'm', 'k' ) ) )
    {
      switch ( $last )
      {
        // если указаны гигабайты, тогда умножаем значение три раза; мегабайты - два раза
        case 'g':
          $val *= 1024;
        case 'm':
          $val *= 1024;
        case 'k':
          $val *= 1024;
      }
    }

    return $val;
  }

  /**
   * Конвертирование из байтов в кБ или мБ
   * @param $bytes
   *
   * @return string
   */
  public static function convertFromBytes( $bytes )
  {
    $bytes /= 1024;

    if ( $bytes > 1024 )
      return number_format( $bytes / 1024, 1 ) . ' MB';
    else
      return number_format( $bytes, 1 ) . ' KB';
  }

  /**
   * Добавить суффикс к сомнительным файлам
   * @param null $suffix - имя суффикса
   */
  public function allowAllTypes( $suffix = null )
  {
    $this->typeCheckingOn = false;

    if( !is_null( $suffix ) )
    {
      if( strpos( $suffix, '.' ) === 0 || $suffix == '' )
        $this->suffix = $suffix;
      else
        $this->suffix = ".$suffix";
    }
  }

  /**
   * Загрузка файлов
   * @param bool|true $renameDuplicates
   */
  public function upload( $renameDuplicates = true )
  {
    $this->renameDuplicates = $renameDuplicates;
    $uploaded = current( $_FILES ); // массив с инфой о файлах

    // если это несколько файлов загружается
    if( is_array( $uploaded[ 'name' ] ) )
    {
      foreach ( $uploaded[ 'name' ] as $key => $value )
      {
        // обработака каждого из загружаемых файлов по отдельности
        $currentFile[ 'name' ] = $uploaded[ 'name' ][ $key ];
        $currentFile[ 'type' ] = $uploaded[ 'type' ][ $key ];
        $currentFile[ 'tmp_name' ] = $uploaded[ 'tmp_name' ][ $key ];
        $currentFile[ 'error' ] = $uploaded[ 'error' ][ $key ];
        $currentFile[ 'size' ] = $uploaded[ 'size' ][ $key ];

        if ( $this->checkFile( $currentFile ) )
          $this->moveFile( $currentFile );
      }
    }
    else
    {
      if ( $this->checkFile( $uploaded ) )
        $this->moveFile( $uploaded );
    }
  }

  /**
   * Получить сообщения о результате загрузки файлов
   * @return array
   */
  public function getMessages()
  {
    return $this->messages;
  }

  /**
   * Проверка загружаемых файлов
   *
   * @param $file
   *
   * @return bool
   */
  protected function checkFile( $file )
  {
    if( $file[ 'error' ] != 0 )
    {
      $this->getErrorMessage( $file );

      return false;
    }

    if( ! $this->checkSize( $file ) )
      return false;

    if( $this->typeCheckingOn )
    {
      if( ! $this->checkType( $file ) )
        return false;
    }

    $this->checkName( $file );

    return true;
  }

  /**
   * Формирование сообщеня об ошибке при загрузке
   * @param $file
   */
  protected function getErrorMessage( $file )
  {
    switch( $file[ 'error' ] )
    {
      case 1:
      case 2:
        $this->messages[] = $file[ 'name' ] . ' is too big: ( max: ' . self::convertFromBytes( $this->maxSize ) . ' )';
        break;
      case 3:
        $this->messages[] = $file[ 'name' ] . ' was only partially uploaded.';
        break;
      case 4:
        $this->messages[] = 'No file submitted';
        break;
      default:
        $this->messages[] = 'Sorry, there was a problem uploading ' . $file[ 'name' ];
    }
  }

  /**
   * Проверка размера файла
   * @param $file
   *
   * @return bool
   */
  protected function checkSize( $file )
  {
    if( $file[ 'size' ] == 0 )
    {
      $this->messages[] = $file[ 'name' ] . ' is empty.';

      return false;
    }
    elseif( $file[ 'size' ] > $this->maxSize )
    {
      $this->messages = $file[ 'name' ] . ' exceeds the maximum size of file (' . self::convertFromBytes( $this->maxSize ) . ' )';

      return false;
    }
    else
      return true;
  }

  /**
   * Проверка типа файла
   * @param $file
   *
   * @return bool
   */
  protected function checkType( $file )
  {
    if( in_array( $file[ 'type' ], $this->permittedTypes ) )
      return true;
    else
    {
      $this->messages[] = $file[ 'name' ] . ' is not permitted type of file.';

      return false;
    }
  }

  /**
   * Проверка имени файла
   * @param $file - имя файла
   */
  protected function checkName( $file )
  {
    $this->newName = null;
    $nospaces = str_replace( ' ', '_', $file[ 'name' ] ); // заменить пробел на нижнее подчёркивание

    if( $nospaces != $file[ 'name' ] )
      $this->newName = $nospaces;

    $nameparts = pathinfo( $nospaces );
    $extension = isset( $nameparts[ 'extension' ] ) ? $nameparts[ 'extension' ] : '';

    // проверка на тип файла
    if( ! $this->typeCheckingOn && ! empty( $this->suffix ) )
    {
      if( in_array( $extension, $this->notTrusted ) || empty( $extension ) )
        $this->newName = $nospaces . $this->suffix;
    }

    // проверка на дубликат файла
    if( $this->renameDuplicates )
    {
      $name = isset( $this->newName ) ? $this->newName : $file[ 'name' ];
      $existing = scandir( $this->destination );

      if( in_array( $name, $existing ) )
      {
        $i = 1;

        do
        {
          $this->newName = $nameparts[ 'filename' ] . '_' . $i++;

          // формируем новое имя
          if( ! empty( $existing ) )
            $this->newName .= ".$extension";

          // если это подозрительный файл
          if( in_array( $existing, $this->notTrusted ) )
            $this->newName .= $this->suffix;

        }
        while( in_array( $this->newName , $existing) );
      }
    }
  }

  /**
   * Переместить загруженный файл
   * @param $file
   */
  protected function moveFile( $file )
  {
    $fileName = isset( $this->newName ) ? $this->newName : $file[ 'name' ];
    $success = move_uploaded_file( $file[ 'tmp_name' ], $this->destination . $fileName );

    if( $success )
    {
      $result = $file[ 'name' ] . ' was uploaded successfully';

      if ( ! is_null( $this->newName ) )
        $result .= ', and was renamed ' . $this->newName;

      $result .= '.';

      $this->messages[] = $result;
    }
    else
      $this->messages[] = 'Could not upload ' . $file[ 'name' ];
  }
}