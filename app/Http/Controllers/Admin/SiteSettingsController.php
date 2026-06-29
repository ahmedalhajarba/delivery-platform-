<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\StoreSiteSettingRequest;
use App\Http\Requests\UpdateSiteSettingRequest;
use App\Models\SiteSetting;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;
use Yajra\DataTables\Facades\DataTables;

class SiteSettingsController extends Controller
{
    use MediaUploadingTrait;

    public function index(Request $request)
    {
        abort_if(Gate::denies('site_setting_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        if ($request->ajax()) {
            $query = SiteSetting::query()->select(sprintf('%s.*', (new SiteSetting())->table));
            $table = Datatables::of($query);

            $table->addColumn('placeholder', '&nbsp;');
            $table->addColumn('actions', '&nbsp;');

            $table->editColumn('actions', function ($row) {
                $viewGate = 'site_setting_show';
                $editGate = 'site_setting_edit';
                $deleteGate = 'site_setting_delete';
                $crudRoutePart = 'site-settings';

                return view('partials.datatablesActions', compact(
                'viewGate',
                'editGate',
                'deleteGate',
                'crudRoutePart',
                'row'
            ));
            });

            $table->editColumn('id', function ($row) {
                return $row->id ? $row->id : '';
            });
            $table->editColumn('title_ar', function ($row) {
                return $row->title_ar ? $row->title_ar : '';
            });
            $table->editColumn('title_en', function ($row) {
                return $row->title_en ? $row->title_en : '';
            });
            $table->editColumn('logo', function ($row) {
                if ($photo = $row->logo) {
                    return sprintf(
        '<a href="%s" target="_blank"><img src="%s" width="50px" height="50px"></a>',
        $photo->url,
        $photo->thumbnail
    );
                }

                return '';
            });
            $table->editColumn('icon', function ($row) {
                if ($photo = $row->icon) {
                    return sprintf(
        '<a href="%s" target="_blank"><img src="%s" width="50px" height="50px"></a>',
        $photo->url,
        $photo->thumbnail
    );
                }

                return '';
            });
            $table->editColumn('site_footer', function ($row) {
                return $row->site_footer ? $row->site_footer : '';
            });
            $table->editColumn('email', function ($row) {
                return $row->email ? $row->email : '';
            });
            $table->editColumn('phone', function ($row) {
                return $row->phone ? $row->phone : '';
            });
            $table->editColumn('mobile', function ($row) {
                return $row->mobile ? $row->mobile : '';
            });
            $table->editColumn('mobile_b', function ($row) {
                return $row->mobile_b ? $row->mobile_b : '';
            });
            $table->editColumn('mobile_c', function ($row) {
                return $row->mobile_c ? $row->mobile_c : '';
            });
            $table->editColumn('ios_url', function ($row) {
                return $row->ios_url ? $row->ios_url : '';
            });
            $table->editColumn('android_url', function ($row) {
                return $row->android_url ? $row->android_url : '';
            });
            $table->editColumn('harmony_url', function ($row) {
                return $row->harmony_url ? $row->harmony_url : '';
            });
            $table->editColumn('description_ar', function ($row) {
                return $row->description_ar ? $row->description_ar : '';
            });
            $table->editColumn('description_en', function ($row) {
                return $row->description_en ? $row->description_en : '';
            });
            $table->editColumn('key_words_ar', function ($row) {
                return $row->key_words_ar ? $row->key_words_ar : '';
            });
            $table->editColumn('key_words_en', function ($row) {
                return $row->key_words_en ? $row->key_words_en : '';
            });

            $table->rawColumns(['actions', 'placeholder', 'logo', 'icon']);

            return $table->make(true);
        }

        return view('admin.siteSettings.index');
    }

    public function create()
    {
        abort_if(Gate::denies('site_setting_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.siteSettings.create');
    }

    public function store(StoreSiteSettingRequest $request)
    {
        $siteSetting = SiteSetting::create($request->all());

        if ($request->input('logo', false)) {
            $siteSetting->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
        }

        if ($request->input('icon', false)) {
            $siteSetting->addMedia(storage_path('tmp/uploads/' . basename($request->input('icon'))))->toMediaCollection('icon');
        }

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $siteSetting->id]);
        }

        return redirect()->route('admin.site-settings.index');
    }

    public function edit(SiteSetting $siteSetting)
    {
        abort_if(Gate::denies('site_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.siteSettings.edit', compact('siteSetting'));
    }

    public function update(UpdateSiteSettingRequest $request, SiteSetting $siteSetting)
    {
        $siteSetting->update($request->all());

        if ($request->input('logo', false)) {
            if (!$siteSetting->logo || $request->input('logo') !== $siteSetting->logo->file_name) {
                if ($siteSetting->logo) {
                    $siteSetting->logo->delete();
                }
                $siteSetting->addMedia(storage_path('tmp/uploads/' . basename($request->input('logo'))))->toMediaCollection('logo');
            }
        } elseif ($siteSetting->logo) {
            $siteSetting->logo->delete();
        }

        if ($request->input('icon', false)) {
            if (!$siteSetting->icon || $request->input('icon') !== $siteSetting->icon->file_name) {
                if ($siteSetting->icon) {
                    $siteSetting->icon->delete();
                }
                $siteSetting->addMedia(storage_path('tmp/uploads/' . basename($request->input('icon'))))->toMediaCollection('icon');
            }
        } elseif ($siteSetting->icon) {
            $siteSetting->icon->delete();
        }

        return redirect()->route('admin.site-settings.index');
    }

    public function show(SiteSetting $siteSetting)
    {
        abort_if(Gate::denies('site_setting_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        return view('admin.siteSettings.show', compact('siteSetting'));
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('site_setting_create') && Gate::denies('site_setting_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new SiteSetting();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
