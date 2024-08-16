<?php

namespace App\Http\Controllers;

use App\Helpers\Functions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Attachment;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\File as FileModel;

class AttachmentController extends Controller
{
    public $image_extensions = [
        'jpg', 'jpeg', 'jpe', 'jif', 'jfif', 'jfi',
        'png',
        'gif',
        'webp',
        'tiff', 'tif',
        'bmp', 'dib',
        'jp2', 'j2k', 'jpf', 'jpx', 'jpm', 'mj2'
    ];

    public $video_extensions = ['flv', 'mp4', 'm3u8', 'ts', '3gp', 'mov', 'avi', 'wmv', 'webm', 'mkv', 'ogv', 'ogg', 'oga', 'ogx'];

    public $audio_extensions = ['mp3', 'ogg', 'wav', 'amr'];

    public $document_extensions = ["doc", "docx", "pdf", 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf'];

    public function store(Request $request, $id, $model)
    {
        $data = $request->validate([
            'file' => ['required', 'max:20240'],
        ]);

        $main_dir = Str::lower(Str::plural($model));

        $model = "App\\Models\\" . $model;
        $object = $model::where('id', $id)->get()->first();

        $data = Functions::filtered_request_data($data);

        if ($request->file('file'))
        {
            $files = $request->file('file');
            foreach ($files as $file)
            {
                $name = $file->getClientOriginalName();
                $name_splited = explode('.', $name);
                $disk_name = '';
                foreach ($name_splited as $index => $ns)
                {
                    if ($index != count($name_splited) - 1)
                    {
                        $disk_name .= $ns;
                    }
                    else
                    {
                        $disk_name .= '_' . time() . '.';
                        $disk_name .= strtolower($ns);
                    }
                }
                $extension = strtolower($file->getClientOriginalExtension());
                $mimetype = $file->getMimeType();
                $filesize = $file->getSize();

                $directory_path = storage_path('app/public/' . $main_dir . '/' . $object->id);
                if (!FileModel::exists($directory_path))
                {
                    FileModel::makeDirectory($directory_path, 0755, true);
                }

                $path = storage_path('app/public/' . $main_dir . '/' . $object->id . '/' . $disk_name);
                $relative_path = '/storage/' . $main_dir . '/' . $object->id . '/' . $disk_name;

                if (FileModel::exists($path))
                {
                    FileModel::delete($path);
                }

                $file->move($directory_path, $disk_name);
                if (in_array($extension, $this->image_extensions))
                {
                    $data['type'] = "image";
                }
                else if (in_array($extension, $this->video_extensions))
                {
                    $data['type'] = "video";
                }
                else if (in_array($extension, $this->audio_extensions))
                {
                    $data['type'] = "audio";
                }
                else if (in_array($extension, $this->document_extensions))
                {
                    $data['type'] = "document";
                }
                else
                {
                    $data['type'] = "other";
                }

                $data['name'] = $name;
                $data['disk_name'] = $disk_name;
                $data['link'] = $relative_path;
                $data['extension'] = $extension;
                $data['mime'] = $mimetype;
                $data['size'] = $filesize;

                unset($data['file']);
                $attachment = new Attachment($data);
                $object->attachments()->save($attachment);
                $attachment->save();
            }
        }

        // Artisan::call('storage:link');
        // Artisan::call('optimize:clear');

        return response()->json(['uploaded' => 'OK'], 200);
    }

    public function createAttachmentObject($fileName, $mimetype, $extension, $filesize, $id, $model)
    {

        $main_dir = Str::lower(Str::plural($model));

        $model = "App\\Models\\" . $model;
        $object = $model::where('id', $id)->get()->first();

        $name = $fileName;
        $disk_name = $fileName;
        
        $path = storage_path('app/public/' . $main_dir . '/' . $object->id . '/' . $disk_name);
        $relative_path = '/storage/' . $main_dir . '/' . $object->id . '/' . $disk_name;

        if (in_array($extension, $this->image_extensions))
        {
            $data['type'] = "image";
        }
        else if (in_array($extension, $this->video_extensions))
        {
            $data['type'] = "video";
        }
        else if (in_array($extension, $this->audio_extensions))
        {
            $data['type'] = "audio";
        }
        else if (in_array($extension, $this->document_extensions))
        {
            $data['type'] = "document";
        }
        else
        {
            $data['type'] = "other";
        }

        $data['name'] = $name;
        $data['disk_name'] = $disk_name;
        $data['link'] = $relative_path;
        $data['extension'] = $extension;
        $data['mime'] = $mimetype;
        $data['size'] = $filesize;

        $attachment = new Attachment($data);
        $object->attachments()->save($attachment);
        $attachment->save();
        
        return response()->json(['uploaded' => 'OK'], 200);
    }

    public function destroy($id, $model, Attachment $attachment)
    {
        /** @var \App\Models\User */
        $user = Auth::user();

        $main_dir = Str::lower(Str::plural($model));
        $model = "App\\Models\\" . $model;
        $object = $model::where('id', $id)->get()->first();
        $file_name = $attachment->disk_name;
        $file_link = $attachment->link;
        $path = storage_path('app/public/' . $main_dir . '/' . $object->id . '/' . $file_name);
        if (FileModel::exists($path))
        {
            FileModel::delete($path);
        }

        $attachment->delete();

        // Artisan::call('optimize:clear');
        // Artisan::call('storage:link');
        return response()->json(['deleted' => 'OK'], 200);
    }

    public function upload($id, $model, $filesData, $deleteOld = 0)
    {
        try
        {
            $attachments_array = [];
            $main_dir = Str::lower(Str::plural($model));

            $delModelName = $model;

            $model = "App\\Models\\" . $model;
            $object = $model::where('id', $id)->get()->first();

            if ($deleteOld)
            {
                $objectOldAttachments = $object->attachments()->orderBy('created_at')->get()->flatten();
            }

            if (Functions::not_empty($filesData) && count($filesData['files']))
            {
                foreach ($filesData['files'] as $index => $fileObj)
                {
                    $file = $fileObj['file'];
                    $name = $file->getClientOriginalName();
                    $name_splited = explode('.', $name);
                    $disk_name = '';
                    foreach ($name_splited as $index => $ns)
                    {
                        if ($index != count($name_splited) - 1)
                        {
                            $disk_name .= $ns;
                        }
                        else
                        {
                            $disk_name .= '_' . time() . '.';
                            $disk_name .= strtolower($ns);
                        }
                    }
                    $disk_name = preg_replace('/\s+/', '', $disk_name);
                    $extension = strtolower($file->getClientOriginalExtension());
                    $mimetype = $file->getMimeType();
                    $filesize = $file->getSize();

                    $directory_path = storage_path('app/public/' . $main_dir . '/' . $object->id);
                    if (!FileModel::exists($directory_path))
                    {
                        FileModel::makeDirectory($directory_path, 0755, true);
                    }

                    $path = storage_path('app/public/' . $main_dir . '/' . $object->id . '/' . $disk_name);
                    $relative_path = '/storage/' . $main_dir . '/' . $object->id . '/' . $disk_name;

                    if (FileModel::exists($path))
                    {
                        FileModel::delete($path);
                    }

                    $file->move($directory_path, $disk_name);
                    if (in_array($extension, $this->image_extensions))
                    {
                        $data['type'] = "image";
                    }
                    else if (in_array($extension, $this->video_extensions))
                    {
                        $data['type'] = "video";
                    }
                    else if (in_array($extension, $this->audio_extensions))
                    {
                        $data['type'] = "audio";
                    }
                    else if (in_array($extension, $this->document_extensions))
                    {
                        $data['type'] = "document";
                    }
                    else
                    {
                        $data['type'] = "other";
                    }

                    $data['name'] = $name;
                    $data['disk_name'] = $disk_name;
                    $data['link'] = $relative_path;
                    $data['extension'] = $extension;
                    $data['mime'] = $mimetype;
                    $data['size'] = $filesize;

                    unset($data['file']);
                    $attachment = new Attachment($data);
                    $object->attachments()->save($attachment);
                    $attachment->save();
                    array_push($attachments_array, $attachment);
                }
            }

            if ($deleteOld)
            {
                foreach ($objectOldAttachments as $index => $oldAttachment)
                {
                    $this->destroy($id, $delModelName, $oldAttachment);
                }
            }

            // Artisan::call('storage:link');
            // Artisan::call('optimize:clear');

            if (Functions::not_empty($attachment))
            {
                // return $attachment;
            }
            return $attachments_array;
        }
        catch (Exception $e)
        {
            logger($e->getMessage() . ' - ' . $e->getCode() . ' - ' . $e->getLine() . ' - ' . $e->getTraceAsString());
            return false;
        }
    }

    public function singleUpload($id, $model, $file, $deleteOld = 0)
    {
        try
        {
            $attachments_array = [];
            $main_dir = Str::lower(Str::plural($model));

            $delModelName = $model;

            $model = "App\\Models\\" . $model;
            $object = $model::where('id', $id)->get()->first();

            if ($deleteOld)
            {
                $objectOldAttachments = $object->attachments()->orderBy('created_at')->get()->flatten();
            }

            $name = $file->getClientOriginalName();
            $name_splited = explode('.', $name);
            $disk_name = '';
            foreach ($name_splited as $index => $ns)
            {
                if ($index != count($name_splited) - 1)
                {
                    $disk_name .= $ns;
                }
                else
                {
                    $disk_name .= '_' . time() . '.';
                    $disk_name .= strtolower($ns);
                }
            }
            $disk_name = preg_replace('/\s+/', '', $disk_name);
            $extension = strtolower($file->getClientOriginalExtension());
            $mimetype = $file->getMimeType();
            $filesize = $file->getSize();

            $directory_path = storage_path('app/public/' . $main_dir . '/' . $object->id);
            if (!FileModel::exists($directory_path))
            {
                FileModel::makeDirectory($directory_path, 0755, true);
            }

            $path = storage_path('app/public/' . $main_dir . '/' . $object->id . '/' . $disk_name);
            $relative_path = '/storage/' . $main_dir . '/' . $object->id . '/' . $disk_name;

            if (FileModel::exists($path))
            {
                FileModel::delete($path);
            }

            $file->move($directory_path, $disk_name);
            if (in_array($extension, $this->image_extensions))
            {
                $data['type'] = "image";
            }
            else if (in_array($extension, $this->video_extensions))
            {
                $data['type'] = "video";
            }
            else if (in_array($extension, $this->audio_extensions))
            {
                $data['type'] = "audio";
            }
            else if (in_array($extension, $this->document_extensions))
            {
                $data['type'] = "document";
            }
            else
            {
                $data['type'] = "other";
            }

            $data['name'] = $name;
            $data['disk_name'] = $disk_name;
            $data['link'] = $relative_path;
            $data['extension'] = $extension;
            $data['mime'] = $mimetype;
            $data['size'] = $filesize;

            unset($data['file']);
            $attachment = new Attachment($data);
            $object->attachments()->save($attachment);
            $attachment->save();
            array_push($attachments_array, $attachment);

            if ($deleteOld)
            {
                foreach ($objectOldAttachments as $index => $oldAttachment)
                {
                    $this->destroy($id, $delModelName, $oldAttachment);
                }
            }

            // Artisan::call('storage:link');
            // Artisan::call('optimize:clear');

            return $attachments_array;
        }
        catch (Exception $e)
        {
            logger($e->getMessage() . ' - ' . $e->getCode() . ' - ' . $e->getLine() . ' - ' . $e->getTraceAsString());
            return false;
        }
    }
}
