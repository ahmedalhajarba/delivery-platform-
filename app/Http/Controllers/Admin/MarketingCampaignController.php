<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignActivity;
use App\Models\SalesLead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MarketingCampaignController extends Controller
{
    public function index(Request $request)
    {
        $query = MarketingCampaign::with(['assignedTo', 'creator'])->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('campaign_type')) {
            $query->where('campaign_type', $request->campaign_type);
        }
        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('campaign_code', 'like', "%{$s}%");
            });
        }

        $campaigns = $query->paginate(20)->appends($request->query());
        $statuses  = MarketingCampaign::STATUSES;
        $types     = MarketingCampaign::TYPES;
        $channels  = MarketingCampaign::CHANNELS;

        return view('admin.marketing-campaigns.index', compact('campaigns', 'statuses', 'types', 'channels'));
    }

    public function create()
    {
        $types    = MarketingCampaign::TYPES;
        $channels = MarketingCampaign::CHANNELS;
        $salesUsers = User::whereHas('roles', fn($q) => $q->whereIn('title', ['sales', 'sales_manager']))->orderBy('name')->get();
        return view('admin.marketing-campaigns.create', compact('types', 'channels', 'salesUsers'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'campaign_type'     => 'required|in:' . implode(',', array_keys(MarketingCampaign::TYPES)),
            'channel'           => 'required|in:' . implode(',', array_keys(MarketingCampaign::CHANNELS)),
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'budget'            => 'nullable|numeric|min:0',
            'target_leads'      => 'nullable|integer|min:0',
            'target_conversions'=> 'nullable|integer|min:0',
            'target_revenue'    => 'nullable|numeric|min:0',
            'assigned_to'       => 'nullable|exists:users,id',
        ]);

        $campaign = MarketingCampaign::create([
            'name'               => $request->name,
            'campaign_type'      => $request->campaign_type,
            'channel'            => $request->channel,
            'status'             => 'planning',
            'description'        => $request->description,
            'goals'              => $request->goals,
            'target_audience'    => $request->target_audience,
            'start_date'         => $request->start_date,
            'end_date'           => $request->end_date,
            'budget'             => $request->budget ?? 0,
            'target_leads'       => $request->target_leads ?? 0,
            'target_conversions' => $request->target_conversions ?? 0,
            'target_revenue'     => $request->target_revenue ?? 0,
            'assigned_to'        => $request->assigned_to,
            'created_by'         => auth()->id(),
            'notes'              => $request->notes,
        ]);

        return redirect()->route('admin.marketing-campaigns.show', $campaign)
            ->with('success', 'تم إنشاء الحملة التسويقية بنجاح');
    }

    public function show(MarketingCampaign $marketingCampaign)
    {
        $marketingCampaign->load(['assignedTo', 'creator', 'activities.creator', 'leads']);
        $activityTypes = MarketingCampaign::ACTIVITY_TYPES;

        // Pipeline: linked quotations via leads
        $linkedQuotations = \App\Models\Quotation::whereIn('sales_lead_id', $marketingCampaign->leads->pluck('id'))
            ->with('user')
            ->orderByDesc('id')
            ->take(20)
            ->get();

        return view('admin.marketing-campaigns.show', compact('marketingCampaign', 'activityTypes', 'linkedQuotations'));
    }

    public function edit(MarketingCampaign $marketingCampaign)
    {
        $types      = MarketingCampaign::TYPES;
        $channels   = MarketingCampaign::CHANNELS;
        $statuses   = MarketingCampaign::STATUSES;
        $salesUsers = User::whereHas('roles', fn($q) => $q->whereIn('title', ['sales', 'sales_manager']))->orderBy('name')->get();
        return view('admin.marketing-campaigns.edit', compact('marketingCampaign', 'types', 'channels', 'statuses', 'salesUsers'));
    }

    public function update(Request $request, MarketingCampaign $marketingCampaign)
    {
        $request->validate([
            'name'              => 'required|string|max:255',
            'campaign_type'     => 'required|in:' . implode(',', array_keys(MarketingCampaign::TYPES)),
            'channel'           => 'required|in:' . implode(',', array_keys(MarketingCampaign::CHANNELS)),
            'status'            => 'required|in:' . implode(',', array_keys(MarketingCampaign::STATUSES)),
            'start_date'        => 'nullable|date',
            'end_date'          => 'nullable|date|after_or_equal:start_date',
            'budget'            => 'nullable|numeric|min:0',
            'assigned_to'       => 'nullable|exists:users,id',
        ]);

        $marketingCampaign->update($request->only([
            'name', 'campaign_type', 'channel', 'status', 'description', 'goals',
            'target_audience', 'start_date', 'end_date', 'budget',
            'target_leads', 'target_conversions', 'target_revenue',
            'assigned_to', 'notes',
        ]));

        return redirect()->route('admin.marketing-campaigns.show', $marketingCampaign)
            ->with('success', 'تم تحديث الحملة بنجاح');
    }

    public function storeActivity(Request $request, MarketingCampaign $marketingCampaign)
    {
        $request->validate([
            'activity_type'      => 'required|in:' . implode(',', array_keys(MarketingCampaign::ACTIVITY_TYPES)),
            'content'            => 'nullable|string',
            'leads_gained'       => 'nullable|integer|min:0',
            'conversions_gained' => 'nullable|integer|min:0',
            'revenue_gained'     => 'nullable|numeric|min:0',
            'spend_recorded'     => 'nullable|numeric|min:0',
            'activity_at'        => 'nullable|date',
        ]);

        DB::transaction(function () use ($request, $marketingCampaign) {
            $marketingCampaign->activities()->create([
                'activity_type'      => $request->activity_type,
                'content'            => $request->content,
                'leads_gained'       => $request->leads_gained ?? 0,
                'conversions_gained' => $request->conversions_gained ?? 0,
                'revenue_gained'     => $request->revenue_gained ?? 0,
                'spend_recorded'     => $request->spend_recorded ?? 0,
                'activity_at'        => $request->activity_at ?? now(),
                'created_by'         => auth()->id(),
            ]);

            // Update campaign totals
            $marketingCampaign->increment('actual_leads', $request->leads_gained ?? 0);
            $marketingCampaign->increment('actual_conversions', $request->conversions_gained ?? 0);
            $marketingCampaign->increment('actual_revenue', $request->revenue_gained ?? 0);
            $marketingCampaign->increment('actual_spend', $request->spend_recorded ?? 0);
        });

        return back()->with('success', 'تم تسجيل النشاط بنجاح');
    }

    public function attachLead(Request $request, MarketingCampaign $marketingCampaign)
    {
        $request->validate(['lead_id' => 'required|exists:sales_leads,id']);
        $marketingCampaign->leads()->syncWithoutDetaching([$request->lead_id]);
        return back()->with('success', 'تم ربط العميل المحتمل بالحملة');
    }

    public function detachLead(Request $request, MarketingCampaign $marketingCampaign)
    {
        $request->validate(['lead_id' => 'required|exists:sales_leads,id']);
        $marketingCampaign->leads()->detach($request->lead_id);
        return back()->with('success', 'تم فصل العميل المحتمل من الحملة');
    }
}
