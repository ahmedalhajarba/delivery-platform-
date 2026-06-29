<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\City;
use App\Models\User;
use App\Services\Validation\ContactValidation;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ChangePasswordController extends Controller
{
    public function edit()
    {
        abort_if(Gate::denies('profile_password_edit'), Response::HTTP_FORBIDDEN, '403 Forbidden');

        /** @var User|null $user */
        $user = auth()->user();
        abort_if(!$user, Response::HTTP_FORBIDDEN, '403 Forbidden');

        $cities = City::pluck('title_ar', 'id')->prepend(trans('global.pleaseSelect'), '');

        $dialCodeList = collect(config('country_dial_codes.list', []))
            ->pluck('dial_code')
            ->unique()
            ->sortByDesc(fn ($code) => strlen((string) $code))
            ->values()
            ->all();

        $mobileParts = ContactValidation::splitDialCodeAndLocalNumber(
            $user->mobile,
            $dialCodeList,
            config('country_dial_codes.default', ContactValidation::COUNTRY_CODE)
        );

        return view('auth.passwords.edit', compact('cities', 'mobileParts', 'user'));
    }

    public function update(UpdatePasswordRequest $request)
    {
        /** @var User|null $user */
        $user = auth()->user();
        abort_if(!$user, Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user->update($request->validated());

        return redirect()->route('profile.password.edit')->with('message', __('global.change_password_success'));
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        /** @var User|null $user */
        $user = auth()->user();
        abort_if(!$user, Response::HTTP_FORBIDDEN, '403 Forbidden');

        $user->update($request->validated());

        return redirect()->route('profile.password.edit')->with('message', __('global.update_profile_success'));
    }
}
