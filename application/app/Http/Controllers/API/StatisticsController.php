<?php

namespace App\Http\Controllers\API;

use App\Enums\PrivilegeKey;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class StatisticsController extends Controller
{
    public function __invoke(Request $request): JsonResource
    {
        Gate::allowIf($request->user()->hasPrivilege(PrivilegeKey::ViewStatistic));

        $validated = $request->validate([
            'type' => ['required', Rule::in([
                'projects_plain', 'projects_extended',
                'subprojects_plain', 'subprojects_extended',
                'assignments_plain', 'assignments_extended',
            ])],
            'timeframe' => ['required', Rule::in(['daily', 'monthly', 'yearly'])],
            'basis' => ['required', Rule::in(['created', 'completed'])],
        ]);

        $sql = data_get(
            $this->options(),
            "{$validated['type']}.{$validated['timeframe']}.{$validated['basis']}"
        );

        abort_if(blank($sql), Response::HTTP_UNPROCESSABLE_ENTITY, 'No statistics query matches the given parameters.');

        $rows = DB::select($sql, [
            'institution_id' => Auth::user()->institutionId,
        ]);

        return JsonResource::collection(
            array_map(static fn (object $row): array => (array) $row, $rows)
        );
    }

    private function options(): array
    {
        return [
            'projects_plain' => [
                'daily' => [
                    'created' => $this->projectsDailyStatisticsByCreationTimeSql(),
                    'completed' => $this->projectsDailyStatisticsByCompletionTimeSql(),
                ],
                'monthly' => [
                    'created' => $this->projectsMonthlyStatisticsByCreationTimeSql(),
                    'completed' => $this->projectsMonthlyStatisticsByCompletionTimeSql(),
                ],
                'yearly' => [
                    'created' => $this->projectsYearlyStatisticsByCreationTimeSql(),
                    'completed' => $this->projectsYearlyStatisticsByCompletionTimeSql(),
                ],
            ],
            'projects_extended' => [
                'daily' => [
                    'created' => $this->projectsExtendedDailyStatisticsByCreationTimeSql(),
                    'completed' => $this->projectsExtendedDailyStatisticsByCompletionTimeSql(),
                ],
                'monthly' => [
                    'created' => $this->projectsExtendedMonthlyStatisticsByCreationTimeSql(),
                    'completed' => $this->projectsExtendedMonthlyStatisticsByCompletionTimeSql(),
                ],
                'yearly' => [
                    'created' => $this->projectsExtendedYearlyStatisticsByCreationTimeSql(),
                    'completed' => $this->projectsExtendedYearlyStatisticsByCompletionTimeSql(),
                ],
            ],
            'subprojects_plain' => [
                'daily' => [
                    'created' => $this->subProjectsDailyStatisticsByCreationTimeSql(),
                    'completed' => $this->subProjectsDailyStatisticsByAcceptanceTimeSql(),
                ],
                'monthly' => [
                    'created' => $this->subProjectsMonthlyStatisticsByCreationTimeSql(),
                    'completed' => $this->subProjectsMonthlyStatisticsByAcceptanceTimeSql(),
                ],
                'yearly' => [
                    'created' => $this->subProjectsYearlyStatisticsByCreationTimeSql(),
                    'completed' => $this->subProjectsYearlyStatisticsByAcceptanceTimeSql(),
                ],
            ],
            'subprojects_extended' => [
                'daily' => [
                    'created' => $this->subProjectsExtendedDailyStatisticsByCreationTimeSql(),
                    'completed' => $this->subProjectsExtendedDailyStatisticsByAcceptanceTimeSql(),
                ],
                'monthly' => [
                    'created' => $this->subProjectsExtendedMonthlyStatisticsByCreationTimeSql(),
                    'completed' => $this->subProjectsExtendedMonthlyStatisticsByAcceptanceTimeSql(),
                ],
                'yearly' => [
                    'created' => $this->subProjectsExtendedYearlyStatisticsByCreationTimeSql(),
                    'completed' => $this->subProjectsExtendedYearlyStatisticsByAcceptanceTimeSql(),
                ],
            ],
            'assignments_plain' => [
                'daily' => [
                    'created' => $this->assignmentsDailyStatisticsByCreationTimeSql(),
                    'completed' => $this->assignmentsDailyStatisticsByAcceptanceTimeSql(),
                ],
                'monthly' => [
                    'created' => $this->assignmentsMonthlyStatisticsByCreationTimeSql(),
                    'completed' => $this->assignmentsMonthlyStatisticsByAcceptanceTimeSql(),
                ],
                'yearly' => [
                    'created' => $this->assignmentsYearlyStatisticsByCreationTimeSql(),
                    'completed' => $this->assignmentsYearlyStatisticsByAcceptanceTimeSql(),
                ],
            ],
            'assignments_extended' => [
                'daily' => [
                    'created' => $this->assignmentsExtendedDailyStatisticsByCreationTimeSql(),
                    'completed' => $this->assignmentsExtendedDailyStatisticsByAcceptanceTimeSql(),
                ],
                'monthly' => [
                    'created' => $this->assignmentsExtendedMonthlyStatisticsByCreationTimeSql(),
                    'completed' => $this->assignmentsExtendedMonthlyStatisticsByAcceptanceTimeSql(),
                ],
                'yearly' => [
                    'created' => $this->assignmentsExtendedYearlyStatisticsByCreationTimeSql(),
                    'completed' => $this->assignmentsExtendedYearlyStatisticsByAcceptanceTimeSql(),
                ],
            ],
        ];
    }

    private function projectsDailyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('day', p.created_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.created_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.created_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status
            ORDER BY
                period DESC,
                is_verbal,
                status;
        EOT;
    }

    private function projectsExtendedDailyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('day', p.created_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                tg.tag_id,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
                AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.created_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.created_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status,
                tg.tag_id
            ORDER BY
                period DESC,
                is_verbal,
                status,
                tag_id;
        EOT;
    }

    private function projectsMonthlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('month', p.created_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.created_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.created_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status
            ORDER BY
                period DESC,
                is_verbal,
                status;
        EOT;
    }

    private function projectsExtendedMonthlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('month', p.created_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                tg.tag_id,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
                AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.created_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.created_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status,
                tg.tag_id
            ORDER BY
                period DESC,
                is_verbal,
                status,
                tag_id;
        EOT;
    }

    private function projectsYearlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('year', p.created_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.created_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.created_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status
            ORDER BY
                period DESC,
                is_verbal,
                status
        EOT;
    }

    private function projectsExtendedYearlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('year', p.created_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                tg.tag_id,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
                AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.created_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.created_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status,
                tg.tag_id
            ORDER BY
                period DESC,
                is_verbal,
                status,
                tag_id;
        EOT;
    }

    private function projectsDailyStatisticsByCompletionTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('day', p.accepted_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.accepted_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status
            ORDER BY
                period DESC,
                is_verbal,
                status;
        EOT;
    }

    private function projectsExtendedDailyStatisticsByCompletionTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('day', p.accepted_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                tg.tag_id,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
                AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.accepted_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status,
                tg.tag_id
            ORDER BY
                period DESC,
                is_verbal,
                status,
                tag_id;
        EOT;
    }

    private function projectsMonthlyStatisticsByCompletionTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('month', p.accepted_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.accepted_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status
            ORDER BY
                period DESC,
                is_verbal,
                status;
        EOT;
    }

    private function projectsExtendedMonthlyStatisticsByCompletionTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('month', p.accepted_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                tg.tag_id,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
                AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.accepted_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status,
                tg.tag_id
            ORDER BY
                period DESC,
                is_verbal,
                status,
                tag_id;
        EOT;
    }

    private function projectsYearlyStatisticsByCompletionTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('year', p.accepted_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.accepted_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status
            ORDER BY
                period DESC,
                is_verbal,
                status;
        EOT;
    }


    private function projectsExtendedYearlyStatisticsByCompletionTimeSql(): string
    {
        return <<<EOT
            WITH project_discounts AS (
                SELECT project_id, SUM(discount_amount) AS total_discount
                FROM sub_projects
                WHERE deleted_at IS NULL
                GROUP BY project_id
            )
            SELECT
                date_trunc('year', p.accepted_at) AS period,
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false) AS is_verbal,
                p.status,
                tg.tag_id,
                COUNT(*) AS projects_count,
                SUM(COALESCE(p.price, 0)) AS total_price,
                COALESCE(SUM(pd.total_discount), 0) AS total_discount
            FROM projects p
            LEFT JOIN cached_classifier_values cv
                ON cv.id = p.type_classifier_value_id
               AND cv.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
                AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN project_discounts pd
                ON pd.project_id = p.id
            WHERE p.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.accepted_at),
                COALESCE(cv.type = 'PROJECT_TYPE' AND cv.value = 'ORAL_TRANSLATION', false),
                p.status,
                tg.tag_id
            ORDER BY
                period DESC,
                is_verbal,
                status,
                tag_id;
        EOT;
    }

    private function subProjectsDailyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('day', sp.created_at) AS period,
                p.type_classifier_value_id,
                sp.status,
                COUNT(*) AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)          AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)      AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)    AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND sp.created_at IS NOT NULL
            GROUP BY
                date_trunc('day', sp.created_at),
                p.type_classifier_value_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.status;
        EOT;
    }

    private function subProjectsExtendedDailyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('day', sp.created_at) AS period,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status,
                COUNT(*)                             AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)           AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)       AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0)  AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)       AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)     AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)       AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)     AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND sp.created_at IS NOT NULL
            GROUP BY
                date_trunc('day', sp.created_at),
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status;
        EOT;
    }

    private function subProjectsMonthlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('month', sp.created_at) AS period,
                p.type_classifier_value_id,
                sp.status,
                COUNT(*) AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)          AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)      AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)    AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND sp.created_at IS NOT NULL
            GROUP BY
                date_trunc('month', sp.created_at),
                p.type_classifier_value_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.status;
        EOT;
    }

    private function subProjectsExtendedMonthlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('month', sp.created_at) AS period,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status,
                COUNT(*)                             AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)           AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)       AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0)  AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)       AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)     AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)       AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)     AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND sp.created_at IS NOT NULL
            GROUP BY
                date_trunc('month', sp.created_at),
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status;
        EOT;
    }

    private function subProjectsYearlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('year', sp.created_at) AS period,
                p.type_classifier_value_id,
                sp.status,
                COUNT(*) AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)          AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)      AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)    AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND sp.created_at IS NOT NULL
            GROUP BY
                date_trunc('year', sp.created_at),
                p.type_classifier_value_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.status;
        EOT;
    }

    private function subProjectsExtendedYearlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('year', sp.created_at) AS period,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status,
                COUNT(*)                             AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)           AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)       AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0)  AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)       AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)     AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)       AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)     AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND sp.created_at IS NOT NULL
            GROUP BY
                date_trunc('year', sp.created_at),
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status;
        EOT;
    }

    private function subProjectsDailyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('day', p.accepted_at) AS period,
                p.type_classifier_value_id,
                sp.status,
                COUNT(*) AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)          AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)      AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)    AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.accepted_at),
                p.type_classifier_value_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.status;
        EOT;
    }

    private function subProjectsExtendedDailyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('day', p.accepted_at) AS period,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status,
                COUNT(*)                             AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)           AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)       AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0)  AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)       AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)     AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)       AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)     AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.accepted_at),
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status;
        EOT;
    }

    private function subProjectsMonthlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('month', p.accepted_at) AS period,
                p.type_classifier_value_id,
                sp.status,
                COUNT(*) AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)          AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)      AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)    AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.accepted_at),
                p.type_classifier_value_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.status;
        EOT;
    }

    private function subProjectsExtendedMonthlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('month', p.accepted_at) AS period,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status,
                COUNT(*)                             AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)           AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)       AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0)  AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)       AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)     AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)       AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)     AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.accepted_at),
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status;
        EOT;
    }

    private function subProjectsYearlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('year', p.accepted_at) AS period,
                p.type_classifier_value_id,
                sp.status,
                COUNT(*) AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)          AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)      AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)    AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.accepted_at),
                p.type_classifier_value_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.status;
        EOT;
    }

    private function subProjectsExtendedYearlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH subproject_volumes AS (
                SELECT
                    a.sub_project_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM assignments a
                LEFT JOIN volumes v
                    ON v.assignment_id = a.id
                   AND v.deleted_at IS NULL
                LEFT JOIN sub_projects sp
                    ON sp.id = a.sub_project_id
                   AND sp.deleted_at IS NULL
                LEFT JOIN projects p
                    ON p.id = sp.project_id
                   AND p.deleted_at IS NULL
                WHERE p.institution_id = :institution_id
                GROUP BY a.sub_project_id
            )
            SELECT
                date_trunc('year', p.accepted_at) AS period,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status,
                COUNT(*)                             AS sub_projects_count,
                COALESCE(SUM(sp.price), 0)           AS total_price,
                COALESCE(SUM(sp.discount_amount), 0) AS total_discount,
                COALESCE(SUM(sv.vol_words), 0)       AS volume_words,
                COALESCE(SUM(sv.vol_characters), 0)  AS volume_characters,
                COALESCE(SUM(sv.vol_pages), 0)       AS volume_pages,
                COALESCE(SUM(sv.vol_minutes), 0)     AS volume_minutes,
                COALESCE(SUM(sv.vol_hours), 0)       AS volume_hours,
                COALESCE(SUM(sv.vol_min_fee), 0)     AS volume_min_fee
            FROM sub_projects sp
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN subproject_volumes sv
                ON sv.sub_project_id = sp.id
            WHERE sp.deleted_at IS NULL
                AND p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.accepted_at),
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status
            ORDER BY
                period DESC,
                p.type_classifier_value_id,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                sp.status;
        EOT;
    }






    private function assignmentsDailyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('day', a.created_at) AS period,
                jd.job_short_name,
                a.status,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            WHERE p.institution_id = :institution_id
                AND a.created_at IS NOT NULL
            GROUP BY
                date_trunc('day', a.created_at),
                jd.job_short_name,
                a.status
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status;
        EOT;
    }

    private function assignmentsExtendedDailyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('day', a.created_at) AS period,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id') AS assignee_institution_id,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN vendors ve
                ON ve.id = a.assigned_vendor_id
               AND ve.deleted_at IS NULL
            LEFT JOIN cached_institution_users ciu
                ON ciu.id = ve.institution_user_id
               AND ciu.deleted_at IS NULL
            WHERE p.institution_id = :institution_id
                AND a.created_at IS NOT NULL
            GROUP BY
                date_trunc('day', a.created_at),
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id')
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                assignee_institution_id;
        EOT;
    }

    private function assignmentsMonthlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('month', a.created_at) AS period,
                jd.job_short_name,
                a.status,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            WHERE p.institution_id = :institution_id
                AND a.created_at IS NOT NULL
            GROUP BY
                date_trunc('month', a.created_at),
                jd.job_short_name,
                a.status
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status;
        EOT;
    }

    private function assignmentsExtendedMonthlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('month', a.created_at) AS period,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id') AS assignee_institution_id,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN vendors ve
                ON ve.id = a.assigned_vendor_id
               AND ve.deleted_at IS NULL
            LEFT JOIN cached_institution_users ciu
                ON ciu.id = ve.institution_user_id
               AND ciu.deleted_at IS NULL
            WHERE p.institution_id = :institution_id
                AND a.created_at IS NOT NULL
            GROUP BY
                date_trunc('month', a.created_at),
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id')
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                assignee_institution_id;
        EOT;
    }

    private function assignmentsYearlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('year', a.created_at) AS period,
                jd.job_short_name,
                a.status,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            WHERE p.institution_id = :institution_id
                AND a.created_at IS NOT NULL
            GROUP BY
                date_trunc('year', a.created_at),
                jd.job_short_name,
                a.status
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status;
        EOT;
    }

    private function assignmentsExtendedYearlyStatisticsByCreationTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('year', a.created_at) AS period,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id') AS assignee_institution_id,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN vendors ve
                ON ve.id = a.assigned_vendor_id
               AND ve.deleted_at IS NULL
            LEFT JOIN cached_institution_users ciu
                ON ciu.id = ve.institution_user_id
               AND ciu.deleted_at IS NULL
            WHERE p.institution_id = :institution_id
                AND a.created_at IS NOT NULL
            GROUP BY
                date_trunc('year', a.created_at),
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id')
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                assignee_institution_id;
        EOT;
    }

    private function assignmentsDailyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('day', p.accepted_at) AS period,
                jd.job_short_name,
                a.status,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            WHERE p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.accepted_at),
                jd.job_short_name,
                a.status
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status;
        EOT;
    }

    private function assignmentsExtendedDailyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('day', p.accepted_at) AS period,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id') AS assignee_institution_id,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN vendors ve
                ON ve.id = a.assigned_vendor_id
               AND ve.deleted_at IS NULL
            LEFT JOIN cached_institution_users ciu
                ON ciu.id = ve.institution_user_id
               AND ciu.deleted_at IS NULL
            WHERE p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('day', p.accepted_at),
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id')
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                assignee_institution_id;
        EOT;
    }

    private function assignmentsMonthlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('month', p.accepted_at) AS period,
                jd.job_short_name,
                a.status,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            WHERE p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.accepted_at),
                jd.job_short_name,
                a.status
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status;
        EOT;
    }

    private function assignmentsExtendedMonthlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('month', p.accepted_at) AS period,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id') AS assignee_institution_id,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN vendors ve
                ON ve.id = a.assigned_vendor_id
               AND ve.deleted_at IS NULL
            LEFT JOIN cached_institution_users ciu
                ON ciu.id = ve.institution_user_id
               AND ciu.deleted_at IS NULL
            WHERE p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('month', p.accepted_at),
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id')
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                assignee_institution_id;
        EOT;
    }

    private function assignmentsYearlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('year', p.accepted_at) AS period,
                jd.job_short_name,
                a.status,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            WHERE p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.accepted_at),
                jd.job_short_name,
                a.status
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status;
        EOT;
    }

    private function assignmentsExtendedYearlyStatisticsByAcceptanceTimeSql(): string
    {
        return <<<EOT
            WITH assignment_volumes AS (
                SELECT
                    v.assignment_id,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'WORDS')      AS vol_words,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'CHARACTERS') AS vol_characters,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'PAGES')      AS vol_pages,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MINUTES')    AS vol_minutes,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'HOURS')      AS vol_hours,
                    SUM(v.unit_quantity) FILTER (WHERE v.unit_type = 'MIN_FEE')    AS vol_min_fee
                FROM volumes v
                LEFT JOIN assignments a   ON a.id = v.assignment_id
                LEFT JOIN sub_projects sp ON sp.id = a.sub_project_id AND sp.deleted_at IS NULL
                LEFT JOIN projects p      ON p.id = sp.project_id     AND p.deleted_at IS NULL
                WHERE v.deleted_at IS NULL
                  AND p.institution_id = :institution_id
                GROUP BY v.assignment_id
            )
            SELECT
                date_trunc('year', p.accepted_at) AS period,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id') AS assignee_institution_id,
                COUNT(*)                    AS assignments_count,
                COALESCE(SUM(a.price), 0)   AS total_price,
                COALESCE(SUM(a.discount_amount), 0)   AS total_discount,
                COALESCE(SUM(av.vol_words), 0)      AS volume_words,
                COALESCE(SUM(av.vol_characters), 0) AS volume_characters,
                COALESCE(SUM(av.vol_pages), 0)      AS volume_pages,
                COALESCE(SUM(av.vol_minutes), 0)    AS volume_minutes,
                COALESCE(SUM(av.vol_hours), 0)      AS volume_hours,
                COALESCE(SUM(av.vol_min_fee), 0)    AS volume_min_fee
            FROM assignments a
            JOIN sub_projects sp
                ON sp.id = a.sub_project_id
               AND sp.deleted_at IS NULL
            JOIN projects p
                ON p.id = sp.project_id
               AND p.deleted_at IS NULL
            LEFT JOIN job_definitions jd
                ON jd.id = a.job_definition_id
            LEFT JOIN assignment_volumes av
                ON av.assignment_id = a.id
            LEFT JOIN taggables tg
                ON tg.taggable_id = p.id
               AND tg.taggable_type = 'App\Models\Project'
            LEFT JOIN vendors ve
                ON ve.id = a.assigned_vendor_id
               AND ve.deleted_at IS NULL
            LEFT JOIN cached_institution_users ciu
                ON ciu.id = ve.institution_user_id
               AND ciu.deleted_at IS NULL
            WHERE p.institution_id = :institution_id
                AND p.accepted_at IS NOT NULL
            GROUP BY
                date_trunc('year', p.accepted_at),
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                (ciu.institution ->> 'id')
            ORDER BY
                period DESC,
                jd.job_short_name,
                a.status,
                sp.source_language_classifier_value_id,
                sp.destination_language_classifier_value_id,
                tg.tag_id,
                assignee_institution_id;
        EOT;
    }
}
