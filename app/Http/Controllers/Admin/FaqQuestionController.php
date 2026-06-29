<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Traits\MediaUploadingTrait;
use App\Http\Requests\MassDestroyFaqQuestionRequest;
use App\Http\Requests\StoreFaqQuestionRequest;
use App\Http\Requests\UpdateFaqQuestionRequest;
use App\Models\FaqCategory;
use App\Models\FaqQuestion;
use Gate;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\Response;

class FaqQuestionController extends Controller
{
    use MediaUploadingTrait;

    public function index()
    {
        abort_if(Gate::denies('faq_question_access'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $faqQuestions = FaqQuestion::with(['category'])->get();

        $faq_categories = FaqCategory::get();

        return view('admin.faqQuestions.index', compact('faqQuestions', 'faq_categories'));
    }

    public function create()
    {
        abort_if(Gate::denies('faq_question_create'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $categories = FaqCategory::pluck('category', 'id')->prepend(trans('global.pleaseSelect'), '');

        return view('admin.faqQuestions.create', compact('categories'));
    }

    public function store(StoreFaqQuestionRequest $request)
    {
        $faqQuestion = FaqQuestion::create($request->all());

        if ($media = $request->input('ck-media', false)) {
            Media::whereIn('id', $media)->update(['model_id' => $faqQuestion->id]);
        }

        return redirect()->route('admin.faq-questions.index');
    }

    public function edit(FaqQuestion $faqQuestion)
    {
        abort_if(Gate::denies('faq_question_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $categories = FaqCategory::pluck('category', 'id')->prepend(trans('global.pleaseSelect'), '');

        $faqQuestion->load('category');

        return view('admin.faqQuestions.edit', compact('categories', 'faqQuestion'));
    }

    public function update(UpdateFaqQuestionRequest $request, FaqQuestion $faqQuestion)
    {
        $faqQuestion->update($request->all());

        return redirect()->route('admin.faq-questions.index');
    }

    public function show(FaqQuestion $faqQuestion)
    {
        abort_if(Gate::denies('faq_question_show'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $faqQuestion->load('category');

        return view('admin.faqQuestions.show', compact('faqQuestion'));
    }

    public function destroy(FaqQuestion $faqQuestion)
    {
        abort_if(Gate::denies('faq_question_delete'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $faqQuestion->delete();

        return back();
    }

    public function massDestroy(MassDestroyFaqQuestionRequest $request)
    {
        FaqQuestion::whereIn('id', request('ids'))->delete();

        return response(null, Response::HTTP_NO_CONTENT);
    }

    public function storeCKEditorImages(Request $request)
    {
        abort_if(Gate::denies('faq_question_create') && Gate::denies('faq_question_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        $model         = new FaqQuestion();
        $model->id     = $request->input('crud_id', 0);
        $model->exists = true;
        $media         = $model->addMediaFromRequest('upload')->toMediaCollection('ck-media');

        return response()->json(['id' => $media->id, 'url' => $media->getUrl()], Response::HTTP_CREATED);
    }
}
