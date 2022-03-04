@push('css')
    <style>
        .content-container {
            overflow: hidden;
        }

        .table {
            height: 100%;
            width: 100%;
        }

        .viewport-height {
            height: calc(100vh - 240px);
        }
    </style>
@endpush
@section('title')
{{ __('admin::app.leads.title') }}
@stop
@section('navbar-top')
    @if (bouncer()->hasPermission('leads.create'))
        @include('admin::leads.index.view-swither')
        <kanban-filters></kanban-filters>
        <a href="{{ route('admin.leads.create') }}" class="btn btn-primary d-flex align-items-center">
            <i class="mdi mdi-plus me-2 justify-content-center"></i>
            {{ __('admin::app.leads.title') }}
        </a>
    @endif
@stop
<div class="content full-page">
    <div class="table">
        <div class="table-body viewport-height">
            <kanban-component></kanban-component>
        </div>
    </div>
</div>

@push('scripts')
    <script type="text/x-template" id="kanban-filters-tempalte">
        <div class="form-group datagrid-filters flex center m-0 flex-end w-auto">

            <div class="search-filter relative">
                <i class="mdi mdi-magnify absolute mdi-24px m-0" style="--m: 12% 3% !important;"></i>
                <input
                    type="search"
                    class="control rounded ps-3"
                    id="search-field"
                    :placeholder="__('ui.datagrid.search')"
                />
            </div>

            <sidebar-filter :columns="columns"></sidebar-filter>

            <div class="filter-right px-10">
                <div class="filter-btn">
                    <div class="grid-dropdown-header btn btn-outline-secondary d-flex align-items-center" @click="toggleSidebarFilter">
                        <span class="name">{{ __('ui::app.datagrid.filter.title') }}</span>
                        <i class="mdi mdi-plus d-flex justify-content-center ps-2"></i>
                    </div>
                </div>
            </div>
        </div>
    </script>

    <script type="text/x-template" id="kanban-component-tempalte">
        <kanban-board :stages="stage_names" :blocks="blocks" @update-block="updateLeadStage">
            <div v-for="(stage, index) in stage_names" :slot="stage" :key="`stage-${stage}`" class="rounded">
                <h2>
                    @{{ stage }}
                    <span class="float-right">@{{ totalCounts[stage] || 0 }}</span>
                </h2>

                {{-- @if (bouncer()->hasPermission('leads.create'))
                    <a :href="'{{ route('admin.leads.create') }}' + '?stage_id=' + stages[index].id">
                        {{ __('admin::app.leads.create-title') }}
                    </a>
                @endif --}}
            </div>

            <div
                v-for="block in blocks"
                :slot="block.id"
                :key="`block-${block.id}`"
                class="lead-block"
                :class="{ 'rotten': block.rotten_days > 0 ? true : false }"
            >

                <div class="lead-title">@{{ block.title }}</div>

                <div class="icons">
                    <a :href="'{{ route('admin.leads.view') }}/' + block.id" class="icon eye-icon"></a>
                    <i class="icon drag-icon"></i>
                </div>

                <div class="lead-person">
                    <i class="icon avatar-dark-icon"></i>
                        <a :href="`${personIndexUrl}?id[eq]=${block.person_id}`">
                            @{{ block.person_name }}
                        </a>
                </div>

                <div class="lead-cost">
                    <i class="icon dollar-circle-icon"></i>@{{ block.lead_value }}
                </div>
            </div>
        </kanban-board>
    </script>

    <script>
        Vue.component('kanban-filters', {
            template: '#kanban-filters-tempalte',
            data: function () {
                return {
                    debounce: [],
                    columns: {
                        'created_at': {
                            'type' : 'date_range',
                            'label' : "{{ trans('admin::app.datagrid.created_at') }}",
                            'values' : [null, null],
                            'filterable' : true
                        },
                    }
                }
            },

            mounted: function () {
                EventBus.$on('updateFilter', data => {
                    EventBus.$emit('updateKanbanFilter', data);
                });
            },

            methods: {
                toggleSidebarFilter: function () {
                    $('.sidebar-filter').toggleClass('show');
                },
            }
        });

        Vue.component('kanban-component', {

            template: '#kanban-component-tempalte',

            data: function () {
                return {
                    stage_names: [],

                    stages: [],

                    blocks: [],

                    debounce: null,

                    totalCounts: [],
                    personIndexUrl: "{{ route('admin.contacts.persons.index') }}",
                }
            },

            created: function () {
                this.getLeads();

                queueMicrotask(() => {
                    $('#search-field').on('search keyup', ({target}) => {
                        clearTimeout(this.debounce);

                        this.debounce = setTimeout(() => {
                            this.search(target.value)
                        }, 2000);
                    });
                });
            },

            mounted: function () {
                EventBus.$on('updateKanbanFilter', this.updateFilter);
            },

            methods: {
                getLeads: function (searchedKeyword, filterValues) {
                    this.$root.pageLoaded = false;

                    this.$http.get("{{ route('admin.leads.get', request('pipeline_id')) }}" + `${searchedKeyword ? `?search=${searchedKeyword}` : ''}${filterValues || ''}`)
                        .then(response => {
                            this.$root.pageLoaded = true;

                            this.blocks = response.data.blocks;

                            this.totalCounts = response.data.total_count;

                            this.stage_names = Object.values(response.data.stage_names);

                            this.stages = response.data.stages;

                            setTimeout(() => {
                                this.toggleEmptyStateIcon();
                            })
                        })
                        .catch(error => {
                            this.$root.pageLoaded = true;
                        });
                },

                updateLeadStage: function (id, stageName) {
                    var stage = this.stages.filter(stage => stage.name === stageName);

                    this.$http.put("{{ route('admin.leads.update') }}/" + id, {'lead_pipeline_stage_id': stage[0].id})
                        .then(response => {
                            this.getLeads();

                            this.addFlashMessages({message : response.data.message });
                        })
                        .catch(error => {
                            window.flashMessages = [{'type': 'error', 'message': error.response.data.message}];

                            this.$root.addFlashMessages();
                        });
                },

                search: function (searchedKeyword) {
                    this.getLeads(searchedKeyword);
                },

                getStageId: function (stage) {
                    for (let stageId in this.stages) {
                        if (this.stages[stageId] == stage) {
                            return stageId;
                        }
                    }

                    return 0;
                },

                updateFilter: function (data) {
                    let href = data.key ? `?${data.key}[${data.cond}]=${data.value}` : false;

                    this.getLeads(false, href);
                },

                toggleEmptyStateIcon: function () {
                    $('.empty-icon-container').remove();

                    $('ul.drag-inner-list').each((index, item) => {
                        if (! $(item).children('.drag-item').length) {
                            $(item).append(`
                                <div class='empty-icon-container disable-drag'>
                                    <div class="icon-text-container">
                                        <i class='icon empty-kanban-icon'></i>
                                        <span>{{ __('admin::app.leads.no-lead') }}</span>
                                    </div>
                                </div>
                            `)
                        }
                    });
                }
            }
        });
    </script>
@endpush
