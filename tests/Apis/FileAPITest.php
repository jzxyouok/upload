<?php

use Mockery as m;
use Recca0120\Upload\Apis\FileAPI;
use Recca0120\Upload\Exceptions\ChunkedResponseException;

class FileAPITest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function test_upload_single_file()
    {
        /*
        |------------------------------------------------------------
        | Arrange
        |------------------------------------------------------------
        */

        $config = [
            'chunks_path' => __DIR__,
        ];
        $request = m::spy('Illuminate\Http\Request');
        $filesystem = m::spy('Recca0120\Upload\Filesystem');
        $uploadedFile = m::spy('Symfony\Component\HttpFoundation\File\UploadedFile');
        $inputName = 'foo';

        /*
        |------------------------------------------------------------
        | Act
        |------------------------------------------------------------
        */

        $request
            ->shouldReceive('file')->with($inputName)->andReturn($uploadedFile);

        $filesystem
            ->shouldReceive('isDirectory')->with($config['chunks_path'])->andReturn(false)
            ->shouldReceive('files')->with($config['chunks_path'])->andReturn(['file'])
            ->shouldReceive('isFile')->with('file')->andReturn(true)
            ->shouldReceive('lastModified')->with('file')->andReturn(-1);

        $api = new FileAPI($config, $request, $filesystem);

        /*
        |------------------------------------------------------------
        | Assert
        |------------------------------------------------------------
        */

        $this->assertSame($uploadedFile, $api->receive($inputName));

        $filesystem->shouldHaveReceived('isDirectory')->with($config['chunks_path'])->once();
        $filesystem->shouldHaveReceived('makeDirectory')->with($config['chunks_path'], 0777, true, true)->once();
        $request->shouldHaveReceived('header')->with('content-range')->once();
        $request->shouldHaveReceived('file')->with($inputName)->once();
        $filesystem->shouldHaveReceived('files')->with($config['chunks_path'])->once();
        $filesystem->shouldHaveReceived('isFile')->with('file')->once();
        $filesystem->shouldHaveReceived('lastModified')->with('file')->once();
        $filesystem->shouldHaveReceived('delete')->with('file')->once();
    }

    public function test_upload_chunk_file_throw_chunk_response()
    {
        /*
        |------------------------------------------------------------
        | Arrange
        |------------------------------------------------------------
        */

        $config = [
            'chunks_path' => __DIR__,
        ];
        $request = m::spy('Illuminate\Http\Request');
        $filesystem = m::spy('Recca0120\Upload\Filesystem');
        $uploadedFile = m::spy('Symfony\Component\HttpFoundation\File\UploadedFile');
        $inputName = 'foo';

        $token = uniqid();
        $file = __FILE__;
        $originalName = basename($file);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $mimeType = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
        $tmpfile = $config['chunks_path'].'/'.md5($originalName.$token).'.'.$extension;
        $tmpfileExtension = FileAPI::TMPFILE_EXTENSION;

        $start = 5242880;
        $end = 5767167;
        $total = 7845180;

        $contentRange = 'bytes '.$start.'-'.$end.'/'.$total;
        $contentDisposition = 'attachment; filename='.$originalName;
        $input = 'php://input';

        /*
        |------------------------------------------------------------
        | Act
        |------------------------------------------------------------
        */

        $request
            ->shouldReceive('header')->with('content-range')->andReturn($contentRange)
            ->shouldReceive('header')->with('content-type')->andReturn($mimeType)
            ->shouldReceive('header')->with('content-disposition')->andReturn($contentDisposition)
            ->shouldReceive('get')->with('token')->andReturn($token);

        $filesystem
            ->shouldReceive('isDirectory')->with($config['chunks_path'])->andReturn(false)
            ->shouldReceive('extension')->andReturn($extension);

        $api = new FileAPI($config, $request, $filesystem);

        /*
        |------------------------------------------------------------
        | Assert
        |------------------------------------------------------------
        */

        try {
            $api->receive($inputName);
        } catch (ChunkedResponseException $e) {
            $response = $e->getResponse();
            $this->assertSame(201, $response->getStatusCode());
            $this->assertSame($end, $response->headers->get('X-Last-Known-Byte'));
        }

        $filesystem->shouldHaveReceived('isDirectory')->with($config['chunks_path'])->once();
        $filesystem->shouldHaveReceived('makeDirectory')->with($config['chunks_path'], 0777, true, true)->once();
        $request->shouldHaveReceived('header')->with('content-range')->once();
        $request->shouldHaveReceived('get')->with('name')->once();
        $request->shouldHaveReceived('header')->with('content-disposition')->once();
        $request->shouldHaveReceived('header')->with('content-type')->once();
        $filesystem->shouldHaveReceived('extension')->with($originalName)->once();
        $request->shouldHaveReceived('get')->with('token')->once();
        $filesystem->shouldHaveReceived('appendStream')->with($tmpfile.$tmpfileExtension, $input, $start)->once();
    }

    public function test_upload_chunk_file()
    {
        /*
        |------------------------------------------------------------
        | Arrange
        |------------------------------------------------------------
        */

        $config = [
            'chunks_path' => __DIR__,
        ];
        $request = m::spy('Illuminate\Http\Request');
        $filesystem = m::spy('Recca0120\Upload\Filesystem');
        $uploadedFile = m::spy('Symfony\Component\HttpFoundation\File\UploadedFile');
        $inputName = 'foo';

        $token = uniqid();
        $file = __FILE__;
        $originalName = basename($file);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $mimeType = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file);
        $tmpfile = $config['chunks_path'].'/'.md5($originalName.$token).'.'.$extension;
        $tmpfileExtension = FileAPI::TMPFILE_EXTENSION;

        $start = 5242880;
        $end = 7845180;
        $total = 7845180;

        $contentRange = 'bytes '.$start.'-'.$end.'/'.$total;
        $contentDisposition = 'attachment; filename='.$originalName;
        $input = 'php://input';
        $size = $total;

        /*
        |------------------------------------------------------------
        | Act
        |------------------------------------------------------------
        */

        $request
            ->shouldReceive('header')->with('content-range')->andReturn($contentRange)
            ->shouldReceive('header')->with('content-disposition')->andReturn($contentDisposition)
            ->shouldReceive('get')->with('token')->andReturn($token);

        $filesystem
            ->shouldReceive('isDirectory')->with($config['chunks_path'])->andReturn(false)
            ->shouldReceive('extension')->with($originalName)->andReturn($extension)
            ->shouldReceive('mimeType')->with($originalName)->andReturn($mimeType)
            ->shouldReceive('move')->with($tmpfile.$tmpfileExtension, $tmpfile)
            ->shouldReceive('size')->with($tmpfile)->andReturn($total)
            ->shouldReceive('files')->with($config['chunks_path'])->andReturn(['file'])
            ->shouldReceive('isFile')->with('file')->andReturn(true)
            ->shouldReceive('lastModified')->with('file')->andReturn(-1);

        $api = new FileAPI($config, $request, $filesystem);

        /*
        |------------------------------------------------------------
        | Assert
        |------------------------------------------------------------
        */

        $api->receive($inputName);

        $filesystem->shouldHaveReceived('isDirectory')->with($config['chunks_path'])->once();
        $filesystem->shouldHaveReceived('makeDirectory')->with($config['chunks_path'], 0777, true, true)->once();
        $request->shouldHaveReceived('header')->with('content-range')->once();
        $request->shouldHaveReceived('get')->with('name')->once();
        $request->shouldHaveReceived('header')->with('content-disposition')->once();
        $filesystem->shouldHaveReceived('extension')->with($originalName)->once();
        $filesystem->shouldHaveReceived('mimeType')->with($originalName)->once();
        $request->shouldHaveReceived('get')->with('token')->once();
        $filesystem->shouldHaveReceived('appendStream')->with($tmpfile.$tmpfileExtension, $input, $start)->once();
        $filesystem->shouldHaveReceived('move')->with($tmpfile.$tmpfileExtension, $tmpfile)->once();
        $filesystem->shouldHaveReceived('size')->once();
        $filesystem->shouldHaveReceived('createUploadedFile')->with($tmpfile, $originalName, $mimeType, $size)->once();
        $filesystem->shouldHaveReceived('files')->with($config['chunks_path'])->once();
        $filesystem->shouldHaveReceived('isFile')->with('file')->once();
        $filesystem->shouldHaveReceived('lastModified')->with('file')->once();
        $filesystem->shouldHaveReceived('delete')->with('file')->once();
    }

    public function test_completed_response()
    {
        /*
        |------------------------------------------------------------
        | Arrange
        |------------------------------------------------------------
        */

        $config = [
            'chunks_path' => __DIR__,
        ];
        $request = m::spy('Illuminate\Http\Request');
        $filesystem = m::spy('Recca0120\Upload\Filesystem');
        $response = m::spy('Illuminate\Http\JsonResponse');

        /*
        |------------------------------------------------------------
        | Act
        |------------------------------------------------------------
        */

        $api = new FileAPI($config, $request, $filesystem);

        /*
        |------------------------------------------------------------
        | Assert
        |------------------------------------------------------------
        */

        $this->assertSame($response, $api->completedResponse($response));
    }

    public function test_delete_uploaded_file()
    {
        /*
        |------------------------------------------------------------
        | Arrange
        |------------------------------------------------------------
        */

        $config = [
            'chunks_path' => __DIR__,
        ];
        $request = m::spy('Illuminate\Http\Request');
        $filesystem = m::spy('Recca0120\Upload\Filesystem');
        $uploadedFile = m::spy('Symfony\Component\HttpFoundation\File\UploadedFile');
        $file = 'test';

        /*
        |------------------------------------------------------------
        | Act
        |------------------------------------------------------------
        */

        $uploadedFile
            ->shouldReceive('getPathname')->andReturn($file);

        $filesystem
            ->shouldReceive('isFile')->with($file)->andReturn(true)
            ->shouldReceive('delete')->with($file)->andReturn(true);

        $api = new FileAPI($config, $request, $filesystem);

        /*
        |------------------------------------------------------------
        | Assert
        |------------------------------------------------------------
        */

        $this->assertSame($api, $api->deleteUploadedFile($uploadedFile));
        $uploadedFile->shouldHaveReceived('getPathname')->once();
        $filesystem->shouldHaveReceived('isFile')->with($file)->once();
        $filesystem->shouldHaveReceived('delete')->with($file)->once();
    }
}
