<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Upload;
use App\Models\OwnBrandUpload;
use App\Models\OwnBrandProduct;
use Auth;
use Illuminate\Http\Request;
use Image;
use Response;
use Storage;
use Illuminate\Support\Facades\DB;


class AizUploadController extends Controller {
  public function index(Request $request) {
    $all_uploads = (auth()->user()->user_type == 'seller') ? Upload::where('site_name','MAZING')->where('user_id', auth()->user()->id) : Upload::where('site_name','MAZING');
    $search      = null;
    $sort_by     = null;
    if ($request->search != null) {
      $search = $request->search;
      $all_uploads->where('file_original_name', 'like', '%' . $request->search . '%');
    }
    $sort_by = $request->sort;
    switch ($request->sort) {
      case 'newest':
        $all_uploads->orderBy('created_at', 'desc');
        break;
      case 'oldest':
        $all_uploads->orderBy('created_at', 'asc');
        break;
      case 'smallest':
        $all_uploads->orderBy('file_size', 'asc');
        break;
      case 'largest':
        $all_uploads->orderBy('file_size', 'desc');
        break;
      default:
        $all_uploads->orderBy('created_at', 'desc');
        break;
    }
    $all_uploads = $all_uploads->paginate(60)->appends(request()->query());
   
    return (auth()->user()->user_type == 'seller')
    ? view('seller.uploads.index', compact('all_uploads', 'search', 'sort_by'))
    : view('backend.uploaded_files.index', compact('all_uploads', 'search', 'sort_by'));
  }

  public function create() {
   
    return (auth()->user()->user_type == 'seller')
    ? view('seller.uploads.create')
    : view('backend.uploaded_files.create');
  }

  public function uploadProductImages() {
    return (auth()->user()->user_type == 'seller')
    ? view('seller.uploads.create-products')
    : view('backend.uploaded_files.create-products');
  }

  public function show_uploader(Request $request) {
    return view('uploader.aiz-uploader');
  }

  public function upload(Request $request) {
    
    $type = array(
      "jpg"  => "image",
      "jpeg" => "image",
      "png"  => "image",
      "svg"  => "image",
      "webp" => "image",
      "gif"  => "image",
      "mp4"  => "video",
      "mpg"  => "video",
      "mpeg" => "video",
      "webm" => "video",
      "ogg"  => "video",
      "avi"  => "video",
      "mov"  => "video",
      "flv"  => "video",
      "swf"  => "video",
      "mkv"  => "video",
      "wmv"  => "video",
      "wma"  => "audio",
      "aac"  => "audio",
      "wav"  => "audio",
      "mp3"  => "audio",
      "zip"  => "archive",
      "rar"  => "archive",
      "7z"   => "archive",
      "doc"  => "document",
      "txt"  => "document",
      "docx" => "document",
      "pdf"  => "document",
      "csv"  => "document",
      "xml"  => "document",
      "ods"  => "document",
      "xlr"  => "document",
      "xls"  => "document",
      "xlsx" => "document",
    );

    if ($request->hasFile('aiz_file')) {
      $upload    = new Upload;
      $extension = strtolower($request->file('aiz_file')->getClientOriginalExtension());
      
      if (
        env('DEMO_MODE') == 'On' &&
        isset($type[$extension]) &&
        $type[$extension] == 'archive'
      ) {
        return '{}';
      }
      
      if (isset($type[$extension])) {
        $upload->file_original_name = null;
        $arr = explode('.', $request->file('aiz_file')->getClientOriginalName());
        for ($i = 0; $i < count($arr) - 1; $i++) {
          if ($i == 0) {
            $upload->file_original_name .= $arr[$i];
          } else {
            $upload->file_original_name .= "." . $arr[$i];
          }
        }
        
        $path       = $request->file('aiz_file')->store('uploads/all', 'local');
        $thumb_path = $request->file('aiz_file')->store('uploads/all/thumb_', 'local');       
        $size       = $request->file('aiz_file')->getSize();
        
        // echo base_path('public/'); die;
        // Return MIME type ala mimetype extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        // Get the MIME type of the file
        $file_mime = finfo_file($finfo, base_path('public/') . $path);

        if ($type[$extension] == 'image' && get_setting('disable_image_optimization') != 1) {
          try {
            if (mb_substr($upload->file_original_name, 0, 2) == 'MZ') {
              $img     = Image::canvas(800, 800, '#ffffff');
              $content = Image::make($request->file('aiz_file')->getRealPath())->encode();
              $content->resize(750, 750, function ($constraint) {
                $constraint->aspectRatio();
              });
              $img->insert($content, 'center');
              $img->save(base_path('public/') . $path);
              clearstatcache();
              $size = $img->filesize();
            } else {
              $img    = Image::make($request->file('aiz_file')->getRealPath())->encode();
              $height = $img->height();
              $width  = $img->width();
              if ($width > $height && $width > 1500) {
                $img->resize(1500, null, function ($constraint) {
                  $constraint->aspectRatio();
                });
              } elseif ($height > 1500) {
                $img->resize(null, 800, function ($constraint) {
                  $constraint->aspectRatio();
                });
              }
              $img->save(base_path('public/') . $path);
              clearstatcache();
              $size = $img->filesize();
            }
          } catch (\Exception $e) {
            //dd($e);
          }
        }

        // if (env('FILESYSTEM_DRIVER') == 's3') {
        //   Storage::disk('s3')->put(
        //     $path,
        //     file_get_contents(base_path('public/') . $path),
        //     [
        //       'visibility'  => 'public',
        //       'ContentType' => $extension == 'svg' ? 'image/svg+xml' : $file_mime,
        //     ]
        //   );
        //   if (($arr[0] != 'updates') && (mb_substr($upload->file_original_name, 0, 2) != 'MZ')) {
        //     unlink(base_path('public/') . $path);
        //   }
        // }

        $upload->extension = $extension;
        $upload->file_name = $path;
        $upload->user_id   = Auth::user()->id;
        $upload->type      = $type[$upload->extension];
        $upload->file_size = $size;
        $upload->save();

        if (mb_substr($upload->file_original_name, 0, 2) == 'MZ') {
          $product_warehouse = ProductWarehouse::where('part_no', mb_substr($upload->file_original_name, 0, 7))->first();
          $product           = Product::find($product_warehouse->product_id);
          $thumb             = Image::canvas(300, 300, '#ffffff');
          $thumb_content     = Image::make($request->file('aiz_file')->getRealPath())->encode();
          $thumb_content->resize(250, 250, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
          });
          $thumb->insert($thumb_content, 'center');
          $thumb->save(base_path('public/') . $thumb_path);
          clearstatcache();
          $thumbsize = $thumb->filesize();
          // if (env('FILESYSTEM_DRIVER') == 's3') {
          //   Storage::disk('s3')->put(
          //     $thumb_path,
          //     file_get_contents(base_path('public/') . $thumb_path),
          //     [
          //       'visibility'  => 'public',
          //       'ContentType' => $extension == 'svg' ? 'image/svg+xml' : $file_mime,
          //     ]
          //   );
          //   unlink(base_path('public/') . $path);
          //   unlink(base_path('public/') . $thumb_path);
          // }
          $thumb_upload                     = new Upload;
          $thumb_upload->file_original_name = $upload->file_original_name;
          $thumb_upload->extension          = $extension;
          $thumb_upload->file_name          = $thumb_path;
          $thumb_upload->user_id            = Auth::user()->id;
          $thumb_upload->type               = $type[$thumb_upload->extension];
          $thumb_upload->file_size          = $size;
          $thumb_upload->save();
          if (!$product->thumbnail_img) {
            $product->thumbnail_img = $thumb_upload->id;
          }
          if ($product->photos) {
            $product->photos .= ',' . $upload->id;
          } else {
            $product->photos = $upload->id;
          }
          $product->save();
        }
      }
      return '{}';
    }
  }


  public function get_uploaded_files(Request $request) {
    
    $uploads = Upload::where('user_id', Auth::user()->id);
    if ($request->search != null) {
      $uploads->where('file_original_name', 'like', '%' . $request->search . '%');
    }
    if ($request->sort != null) {
      switch ($request->sort) {
        case 'newest':
          $uploads->orderBy('created_at', 'desc');
          break;
        case 'oldest':
          $uploads->orderBy('created_at', 'asc');
          break;
        case 'smallest':
          $uploads->orderBy('file_size', 'asc');
          break;
        case 'largest':
          $uploads->orderBy('file_size', 'desc');
          break;
        default:
          $uploads->orderBy('created_at', 'desc');
          break;
      }
    }
    return $uploads->paginate(60)->appends(request()->query());
  }

  // public function destroy($id) {
  //   $upload = Upload::findOrFail($id);
  //   if (auth()->user()->user_type == 'seller' && $upload->user_id != auth()->user()->id) {
  //     flash(translate("You don't have permission for deleting this!"))->error();
  //     return back();
  //   }
  //   try {
  //     if (env('FILESYSTEM_DRIVER') == 's3') {
  //       Storage::disk('s3')->delete($upload->file_name);
  //       if (file_exists(public_path() . '/' . $upload->file_name)) {
  //         unlink(public_path() . '/' . $upload->file_name);
  //       }
  //     } else {
  //       unlink(public_path() . '/' . $upload->file_name);
  //     }
  //     $upload->delete();
  //     flash(translate('File deleted successfully'))->success();
  //   } catch (\Exception $e) {
  //     $upload->delete();
  //     flash(translate('File deleted successfully'))->success();
  //   }
  //   return back();
  // }
  public function destroy($id) {
    try {

        // Fetch the upload using Query Builder
        $upload = DB::table('uploads')->where('id', $id)->first();
        
        
        if (!$upload) {
            flash(translate("File not found!"))->error();
            return back();
        }

        if (auth()->user()->user_type == 'seller' && $upload->user_id != auth()->user()->id) {
            flash(translate("You don't have permission for deleting this!"))->error();
            return back();
        }

        $uploadThubm = DB::table('uploads')->where('parent_id', $id)->first();
        if ($uploadThubm) {
          if(mb_substr($uploadThubm->file_original_name, 0, 2) == 'IM'){
            $productThumb = OwnBrandProduct::where('part_no',$uploadThubm->file_original_name)->where('thumbnail_img',$uploadThubm->id)->first();
            if($productThumb !==NULL){
              $productThumb->thumbnail_img = '';
              $productThumb->save();
            }
          }
          $thumbPath = public_path() . '/' . $uploadThubm->file_name;
          if (file_exists($thumbPath)) {
              unlink($thumbPath);
          }
          DB::table('uploads')->where('id', $uploadThubm->id)->delete();
        }   

        
        if(mb_substr($upload->file_original_name, 0, 2) == 'IM'){
          $product = OwnBrandProduct::where('part_no',$upload->file_original_name)->first();
          if ($product) {
              $photos = explode(',', $product->photos);
              $updatedPhotos = array_filter($photos, function($photoId) use ($id) {
                  return $photoId != $id;
              });
              $product->photos = implode(',', $updatedPhotos);
              $product->save();
          }
          $filePath = public_path() . '/' . $upload->file_name;
          if (file_exists($filePath)) {
              unlink($filePath);
          }
          DB::table('uploads')->where('id', $id)->delete();
        }
             
        flash(translate('File deleted successfully'))->success();
    } catch (\Exception $e) {
        // Delete the record even if there is an exception (file might not exist)
        DB::table('uploads')->where('id', $id)->delete();
        flash(translate('File deleted successfully'))->success();
    }

    return back();
}




  public function bulk_uploaded_files_delete(Request $request) {
    if ($request->id) {
      foreach ($request->id as $file_id) {
        $this->destroy($file_id);
      }
      return 1;
    } else {
      return 0;
    }
  }

  public function get_preview_files(Request $request) {
    $ids            = explode(',', $request->ids);
    $files          = Upload::whereIn('id', $ids)->get();
    $new_file_array = [];
    foreach ($files as $file) {
      $file['file_name'] = env('UPLOADS_BASE_URL') . '/' . $file->file_name;

      // $file['file_name'] = my_asset($file->file_name);
      if ($file->external_link) {
        $file['file_name'] = $file->external_link;
      }
      $new_file_array[] = $file;
    }
    // dd($new_file_array);
    return $new_file_array;
    // return $files;
  }

  public function all_file() {
    $uploads = Upload::all();
    foreach ($uploads as $upload) {
      try {
        if (env('FILESYSTEM_DRIVER') == 's3') {
          Storage::disk('s3')->delete($upload->file_name);
          if (file_exists(public_path() . '/' . $upload->file_name)) {
            unlink(public_path() . '/' . $upload->file_name);
          }
        } else {
          unlink(public_path() . '/' . $upload->file_name);
        }
        $upload->delete();
        flash(translate('File deleted successfully'))->success();
      } catch (\Exception $e) {
        $upload->delete();
        flash(translate('File deleted successfully'))->success();
      }
    }

    Upload::query()->truncate();

    return back();
  }

  //Download project attachment
  public function attachment_download($id) {
    $project_attachment = Upload::find($id);
    try {
      $file_path = public_path($project_attachment->file_name);
      return Response::download($file_path);
    } catch (\Exception $e) {
      flash(translate('File does not exist!'))->error();
      return back();
    }
  }
  //Download project attachment
  public function file_info(Request $request) {
    $file = Upload::findOrFail($request['id']);

    return (auth()->user()->user_type == 'seller')
    ? view('seller.uploads.info', compact('file'))
    : view('backend.uploaded_files.info', compact('file'));
  }

  public function own_brand_all_file(Request $request) {
    $all_uploads = (auth()->user()->user_type == 'seller') ? Upload::where('user_id', auth()->user()->id)->where('site_name','IMPEX')->whereNull('parent_id') : Upload::where('site_name','IMPEX')->whereNull('parent_id');
    $search      = null;
    $sort_by     = null;
    if ($request->search != null) {
      $search = $request->search;
      $all_uploads->where('file_original_name', 'like', '%' . $request->search . '%');
    }
    $sort_by = $request->sort;
    switch ($request->sort) {
      case 'newest':
        $all_uploads->orderBy('created_at', 'desc');
        break;
      case 'oldest':
        $all_uploads->orderBy('created_at', 'asc');
        break;
      case 'smallest':
        $all_uploads->orderBy('file_size', 'asc');
        break;
      case 'largest':
        $all_uploads->orderBy('file_size', 'desc');
        break;
      default:
        $all_uploads->orderBy('created_at', 'desc');
        break;
    }
    $all_uploads = $all_uploads->paginate(60)->appends(request()->query());
   
    return (auth()->user()->user_type == 'seller')
    ? view('seller.uploads.index', compact('all_uploads', 'search', 'sort_by'))
    : view('backend.uploaded_files.own_brand_all_file', compact('all_uploads', 'search', 'sort_by'));
  }

  public function own_brand_file_create() {
   
    return view('backend.uploaded_files.own_brand_file_create');
  }

  public function own_brand_file_upload(Request $request) {
    $type = array(
      "jpg"  => "image",
      "jpeg" => "image",
      "png"  => "image",
      "svg"  => "image",
      "webp" => "image",
      "gif"  => "image",
      "mp4"  => "video",
      "mpg"  => "video",
      "mpeg" => "video",
      "webm" => "video",
      "ogg"  => "video",
      "avi"  => "video",
      "mov"  => "video",
      "flv"  => "video",
      "swf"  => "video",
      "mkv"  => "video",
      "wmv"  => "video",
      "wma"  => "audio",
      "aac"  => "audio",
      "wav"  => "audio",
      "mp3"  => "audio",
      "zip"  => "archive",
      "rar"  => "archive",
      "7z"   => "archive",
      "doc"  => "document",
      "txt"  => "document",
      "docx" => "document",
      "pdf"  => "document",
      "csv"  => "document",
      "xml"  => "document",
      "ods"  => "document",
      "xlr"  => "document",
      "xls"  => "document",
      "xlsx" => "document",
    );

    if ($request->hasFile('aiz_file')) {
      $upload    = new Upload;
      $extension = strtolower($request->file('aiz_file')->getClientOriginalExtension());

      if ( env('DEMO_MODE') == 'On' && isset($type[$extension]) && $type[$extension] == 'archive' ) {
        return '{}';
      }

      if (isset($type[$extension])) {
        $upload->file_original_name = null;
        $arr                        = explode('.', $request->file('aiz_file')->getClientOriginalName());
        for ($i = 0; $i < count($arr) - 1; $i++) {
          if ($i == 0) {
            $upload->file_original_name .= $arr[$i];
          } else {
            $upload->file_original_name .= "." . $arr[$i];
          }
        }

        $path       = $request->file('aiz_file')->store('uploads/all', 'local');
        $thumb_path = $request->file('aiz_file')->store('uploads/all/thumb_', 'local');
        $size       = $request->file('aiz_file')->getSize();

        // Return MIME type ala mimetype extension
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        // Get the MIME type of the file
        $file_mime = finfo_file($finfo, base_path('public/') . $path);

        if ($type[$extension] == 'image' && get_setting('disable_image_optimization') != 1) {
          try {
            if (mb_substr($upload->file_original_name, 0, 2) == 'IM') {
              $img     = Image::canvas(800, 800, '#ffffff');
              $content = Image::make($request->file('aiz_file')->getRealPath())->encode();
              $content->resize(750, 750, function ($constraint) {
                $constraint->aspectRatio();
              });
              $img->insert($content, 'center');
              $img->save(base_path('public/') . $path);
              clearstatcache();
              $size = $img->filesize();
            } else {
              $img    = Image::make($request->file('aiz_file')->getRealPath())->encode();
              $height = $img->height();
              $width  = $img->width();
              if ($width > $height && $width > 1500) {
                $img->resize(1500, null, function ($constraint) {
                  $constraint->aspectRatio();
                });
              } elseif ($height > 1500) {
                $img->resize(null, 800, function ($constraint) {
                  $constraint->aspectRatio();
                });
              }
              $img->save(base_path('public/') . $path);
              clearstatcache();
              $size = $img->filesize();
            }
          } catch (\Exception $e) {
            //dd($e);
          }
        }
        // Sync to google
        // if (env('FILESYSTEM_DRIVER') == 's3') {
        //   Storage::disk('s3')->put(
        //     $path,
        //     file_get_contents(base_path('public/') . $path),
        //     [
        //       'visibility'  => 'public',
        //       'ContentType' => $extension == 'svg' ? 'image/svg+xml' : $file_mime,
        //     ]
        //   );
        //   if (($arr[0] != 'updates') && (mb_substr($upload->file_original_name, 0, 2) != 'MZ')) {
        //     unlink(base_path('public/') . $path);
        //   }
        // }

        $upload->extension = $extension;
        $upload->file_name = $path;
        $upload->user_id   = Auth::user()->id;
        $upload->type      = $type[$upload->extension];
        $upload->file_size = $size;
        $upload->site_name = 'IMPEX';
        $upload->save();

        if (mb_substr($upload->file_original_name, 0, 2) == 'IM') {
          // $product_warehouse = ProductWarehouse::where('part_no', mb_substr($upload->file_original_name, 0, 7))->first();
          $product           = OwnBrandProduct::where('part_no',$upload->file_original_name)->first();
          $thumb             = Image::canvas(300, 300, '#ffffff');
          $thumb_content     = Image::make($request->file('aiz_file')->getRealPath())->encode();
          $thumb_content->resize(250, 250, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
          });
          $thumb->insert($thumb_content, 'center');
          $thumb->save(base_path('public/') . $thumb_path);
          clearstatcache();
          $thumbsize = $thumb->filesize();
          // Sync to google
          // if (env('FILESYSTEM_DRIVER') == 's3') {
          //   Storage::disk('s3')->put(
          //     $thumb_path,
          //     file_get_contents(base_path('public/') . $thumb_path),
          //     [
          //       'visibility'  => 'public',
          //       'ContentType' => $extension == 'svg' ? 'image/svg+xml' : $file_mime,
          //     ]
          //   );
          //   unlink(base_path('public/') . $path);
          //   unlink(base_path('public/') . $thumb_path);
          // }
          $thumb_upload                     = new Upload;
          $thumb_upload->file_original_name = $upload->file_original_name;
          $thumb_upload->parent_id = $upload->id;
          $thumb_upload->extension          = $extension;
          $thumb_upload->file_name          = $thumb_path;
          $thumb_upload->user_id            = Auth::user()->id;
          $thumb_upload->type               = $type[$thumb_upload->extension];
          $thumb_upload->file_size          = $size;
          $thumb_upload->site_name = 'IMPEX';
          $thumb_upload->save();
          if (!$product->thumbnail_img) {
            $product->thumbnail_img = $thumb_upload->id;
          }
          if ($product->photos) {
            $product->photos .= ',' . $upload->id;
          } else {
            $product->photos = $upload->id;
          }
          $product->save();
        }
      }
      return '{}';
    }
  }

  public function get_own_brand_uploaded_files(Request $request) {
    
    $uploads = OwnBrandUpload::where('user_id', Auth::user()->id);
    if ($request->search != null) {
      $uploads->where('file_original_name', 'like', '%' . $request->search . '%');
    }
    if ($request->sort != null) {
      switch ($request->sort) {
        case 'newest':
          $uploads->orderBy('created_at', 'desc');
          break;
        case 'oldest':
          $uploads->orderBy('created_at', 'asc');
          break;
        case 'smallest':
          $uploads->orderBy('file_size', 'asc');
          break;
        case 'largest':
          $uploads->orderBy('file_size', 'desc');
          break;
        default:
          $uploads->orderBy('created_at', 'desc');
          break;
      }
    }
    return $uploads->paginate(60)->appends(request()->query());
  }


}
