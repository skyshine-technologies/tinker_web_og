<?php

namespace App\DataTables;

use App\Models\MarketReview;
use App\Models\CustomField;
use Yajra\DataTables\Services\DataTable;
use Yajra\DataTables\EloquentDataTable;
use Barryvdh\DomPDF\Facade as PDF;

class MarketReviewDataTable extends DataTable
{
    /**
     * custom fields columns
     * @var array
     */
    public static $customFields = [];

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        $dataTable = new EloquentDataTable($query);
        $columns = array_column($this->getColumns(), 'data');
        $dataTable = $dataTable
            ->editColumn('updated_at', function ($market_review) {
                return getDateColumn($market_review, 'updated_at');
            })
            ->addColumn('action', 'market_reviews.datatables_actions')
            ->rawColumns(array_merge($columns, ['action']));

        return $dataTable;
    }

    /**
     * Get query source of dataTable.
     *
     * @param \App\Models\Post $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(MarketReview $model)
    {
        if (auth()->user()->hasRole('admin')) {
            return $model->newQuery()->with("user")->with("market")->select('market_reviews.*');
        } else {
            return $model->newQuery()->with("user")->with("market")
                ->join("user_markets", "user_markets.market_id", "=", "market_reviews.market_id")
                ->where('user_markets.user_id', auth()->id())
                ->select('market_reviews.*');
        }

    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        return $this->builder()
            ->columns($this->getColumns())
            ->minifiedAjax()
            ->addAction(['width' => '80px', 'printable' => false, 'responsivePriority' => '100'])
            ->parameters(array_merge(
                config('datatables-buttons.parameters'), [
                    'language' => json_decode(
                        file_get_contents(base_path('resources/lang/' . app()->getLocale() . '/datatable.json')
                        ), true)
                ]
            ));
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {
        $columns = [
            [
                'data' => 'review',
                'title' => trans('lang.market_review_review'),

            ],
            [
                'data' => 'rate',
                'title' => trans('lang.market_review_rate'),

            ],
            [
                'data' => 'user.name',
                'title' => trans('lang.market_review_user_id'),

            ],
            [
                'data' => 'market.name',
                'title' => trans('lang.market_review_market_id'),

            ],
            [
                'data' => 'updated_at',
                'title' => trans('lang.market_review_updated_at'),
                'searchable' => false,
            ]
        ];

        $hasCustomField = in_array(MarketReview::class, setting('custom_field_models', []));
        if ($hasCustomField) {
            $customFieldsCollection = CustomField::where('custom_field_model', MarketReview::class)->where('in_table', '=', true)->get();
            foreach ($customFieldsCollection as $key => $field) {
                array_splice($columns, $field->order - 1, 0, [[
                    'data' => 'custom_fields.' . $field->name . '.view',
                    'title' => trans('lang.market_review_' . $field->name),
                    'orderable' => false,
                    'searchable' => false,
                ]]);
            }
        }
        return $columns;
    }

    /**
     * Get filename for export.
     *
     * @return string
     */
    protected function filename()
    {
        return 'market_reviewsdatatable_' . time();
    }

    /**
     * Export PDF using DOMPDF
     * @return mixed
     */
    public function pdf()
    {
        $data = $this->getDataForPrint();
        $pdf = PDF::loadView($this->printPreview, compact('data'));
        return $pdf->download($this->filename() . '.pdf');
    }
}