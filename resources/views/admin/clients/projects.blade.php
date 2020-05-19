@extends('layouts.app')

@section('page-title')
    <div class="row bg-title">
        <!-- .page title -->
        <div class="col-lg-6 col-md-4 col-sm-4 col-xs-12">
            <h4 class="page-title"><i class="{{ $pageIcon }}"></i> {{ $pageTitle }}
                @if($client->status =='active')
                    <label class="label label-success" style="font-size: 10px !important;">{{__('app.active')}}</label>
                @else
                    <label class="label label-danger" style="font-size: 10px !important;">{{__('app.inactive')}}</label>
                @endif
            </h4>


        </div>
        <!-- /.page title -->
        <!-- .breadcrumb -->
        <div class="col-lg-6 col-sm-8 col-md-8 col-xs-12">
            <ol class="breadcrumb">
                <li><a href="{{ route('admin.dashboard') }}">@lang('app.menu.home')</a></li>
                <li><a href="{{ route('admin.clients.index') }}">{{ $pageTitle }}</a></li>
                <li class="active">@lang('app.menu.projects')</li>
            </ol>
        </div>
        <div class="col-lg-6 col-sm-8 col-md-8 col-xs-12 text-right">

            <a href="{{ route('admin.clients.edit',$client->id) }}"
               class="btn btn-outline btn-success btn-sm">@lang('modules.lead.edit')
                <i class="fa fa-edit" aria-hidden="true"></i></a>
        </div>
        <!-- /.breadcrumb -->
    </div>
@endsection


@section('content')

    <div class="row">


        <div class="col-md-12">
            <div class="white-box">

                <div class="row">
                    <div class="col-xs-6 b-r"> <strong>@lang('modules.employees.fullName')</strong> <br>
                        <p class="text-muted">{{ ucwords($client->name) }}</p>
                    </div>
                    <div class="col-xs-6"> <strong>@lang('app.mobile')</strong> <br>
                        <p class="text-muted">{{ $client->mobile ?? 'NA'}}</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6 col-xs-6 b-r"> <strong>@lang('app.email')</strong> <br>
                        <p class="text-muted">{{ $client->email }}</p>
                    </div>
                    <div class="col-md-3 col-xs-6"> <strong>@lang('modules.client.companyName')</strong> <br>
                        <p class="text-muted">{{ (!empty($client->client_details) ) ? ucwords($client->client_details->company_name) : 'NA'}}</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6 col-xs-6 b-r"> <strong>@lang('modules.client.website')</strong> <br>
                        <p class="text-muted">{{ $clientDetail->website ?? 'NA' }}</p>
                    </div>
                    <div class="col-md-3 col-xs-6"> <strong>@lang('app.address')</strong> <br>
                        <p class="text-muted">{!!  (!empty($client->client_details)) ? ucwords($client->client_details->address) : 'NA' !!}</p>
                    </div>
                </div>

                {{--Custom fields data--}}
                @if(isset($fields))
                    <div class="row">
                        <hr>
                        @foreach($fields as $field)
                            <div class="col-md-4">
                                <strong>{{ ucfirst($field->label) }}</strong> <br>
                                <p class="text-muted">
                                    @if( $field->type == 'text')
                                        {{$clientDetail->custom_fields_data['field_'.$field->id] ?? '-'}}
                                    @elseif($field->type == 'password')
                                        {{$clientDetail->custom_fields_data['field_'.$field->id] ?? '-'}}
                                    @elseif($field->type == 'number')
                                        {{$clientDetail->custom_fields_data['field_'.$field->id] ?? '-'}}

                                    @elseif($field->type == 'textarea')
                                        {{$clientDetail->custom_fields_data['field_'.$field->id] ?? '-'}}

                                    @elseif($field->type == 'radio')
                                        {{ !is_null($clientDetail->custom_fields_data['field_'.$field->id]) ? $clientDetail->custom_fields_data['field_'.$field->id] : '-' }}
                                    @elseif($field->type == 'select')
                                        {{ (!is_null($clientDetail->custom_fields_data['field_'.$field->id]) && $clientDetail->custom_fields_data['field_'.$field->id] != '') ? $field->values[$clientDetail->custom_fields_data['field_'.$field->id]] : '-' }}
                                    @elseif($field->type == 'checkbox')
                                        {{ !is_null($clientDetail->custom_fields_data['field_'.$field->id]) ? $field->values[$clientDetail->custom_fields_data['field_'.$field->id]] : '-' }}
                                    @elseif($field->type == 'date')
                                        {{ \Carbon\Carbon::parse($clientDetail->custom_fields_data['field_'.$field->id])->format($global->date_format)}}
                                    @endif
                                </p>

                            </div>
                        @endforeach
                    </div>
                @endif

                {{--custom fields data end--}}

            </div>
        </div>

        <div class="col-md-12">

            <section>
                <div class="sttabs tabs-style-line">
                    <div class="white-box">
                        <nav>
                            <ul>
                                <li class="tab-current"><a href="{{ route('admin.clients.projects', $client->id) }}"><i class="icon-layers"></i> <span>@lang('app.menu.projects')</span></a>
                                <li><a href="{{ route('admin.clients.invoices', $client->id) }}"><i class="icon-doc"></i> <span>@lang('app.menu.invoices')</span></a>
                                </li>
                                <li><a href="{{ route('admin.contacts.show', $client->id) }}"><i class="icon-people"></i> <span>@lang('app.menu.contacts')</span></a>
                                @if($gdpr->enable_gdpr)
                                <li><a href="{{ route('admin.clients.gdpr', $client->id) }}"><i class="icon-lock"></i> <span>@lang('modules.gdpr.gdpr')</span></a>
                                @endif
                            </ul>
                        </nav>
                    </div>
                    <div class="content-wrap">
                        <section id="section-line-1" class="show">
                            <div class="row">


                                <div class="col-md-12">
                                    <div class="white-box">
                                        <div class="table-responsive">
                                            <table class="table">
                                                <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>@lang('modules.client.projectName')</th>
                                                    <th>@lang('modules.client.startedOn')</th>
                                                    <th>@lang('modules.client.deadline')</th>
                                                    <th>@lang('app.action')</th>
                                                </tr>
                                                </thead>
                                                <tbody id="timer-list">
                                                @forelse($client->projects as $key=>$project)
                                                    <tr>
                                                        <td>{{ $key+1 }}</td>
                                                        <td>{{ ucwords($project->project_name) }}</td>
                                                        <td>{{ $project->start_date->format($global->date_format) }}</td>
                                                        <td>@if($project->deadline){{ $project->deadline->format($global->date_format) }}@else - @endif</td>
                                                        <td><a href="{{ route('admin.projects.show', $project->id) }}" class="btn btn-info btn-outline btn-sm">@lang('modules.client.viewDetails')</a></td>
                                                    </tr>
                                                @empty

                                                    <tr>
                                                        <td colspan="5" class="text-center">
                                                            <div class="empty-space" style="height: 200px;">
                                                                <div class="empty-space-inner">
                                                                    <div class="icon" style="font-size:30px"><i
                                                                                class="icon-layers"></i>
                                                                    </div>
                                                                    <div class="title m-b-15">@lang('messages.noProjectFound')
                                                                    </div>
                                                                    <div class="subtitle">
                                                                        <a href="{{route('admin.projects.create')}}" type="button" class="btn btn-info"><i
                                                                                    class="zmdi zmdi-arrow-left"></i>
                                                                            @lang('modules.client.assignProject')
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>

                            </div>

                        </section>
                    </div><!-- /content -->
                </div><!-- /tabs -->
            </section>
        </div>


    </div>
    <!-- .row -->

@endsection
