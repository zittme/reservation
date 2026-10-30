<?php

namespace Zittme\Modules\Reservation\Kit;

use Zittme\Modules\Autoinstall\Models\Kit\TableKitImporter;

class Importer extends TableKitImporter
{
	protected function title(): string
	{
		return lang('reservation.reservation') ?: 'Reservation';
	}

	public function getKitInfo(): array
	{
		$info = parent::getKitInfo();
		$info['after'] = ['branch'];
		return $info;
	}

	protected function tables(): array
	{
		return [
			'reservation_resource' => ['pk' => 'resource_srl', 'scope' => 'all', 'refs' => ['module_srl' => 'module']],
			'reservation_form_field' => ['pk' => 'field_srl', 'parent' => ['resource_srl' => 'reservation_resource']],
			'reservation_schedule_rule' => ['pk' => 'rule_srl', 'parent' => ['resource_srl' => 'reservation_resource']],
			'reservation_staff' => ['pk' => 'staff_srl', 'scope' => 'all', 'refs' => ['module_srl' => 'module', 'branch_srl' => 'branch_branch', 'member_srl' => 'member']],
			'reservation_staff_schedule' => ['pk' => 'rule_srl', 'parent' => ['staff_srl' => 'reservation_staff']],
			'reservation_staff_service' => ['pk' => 'map_srl', 'parent' => ['staff_srl' => 'reservation_staff'], 'refs' => ['resource_srl' => 'reservation_resource']],
			'reservation_holiday' => ['pk' => 'holiday_srl', 'scope' => 'all', 'refs' => ['resource_srl' => 'reservation_resource', 'staff_srl' => 'reservation_staff']],
			'reservation_coupon' => ['pk' => 'coupon_srl', 'scope' => 'all', 'refs' => ['module_srl' => 'module', 'resource_srl' => 'reservation_resource'], 'reset' => ['used_count' => 0]],
			'reservation_grade' => ['pk' => 'grade_srl', 'scope' => 'all', 'refs' => ['module_srl' => 'module', 'coupon_srl' => 'reservation_coupon']],
		];
	}
}
