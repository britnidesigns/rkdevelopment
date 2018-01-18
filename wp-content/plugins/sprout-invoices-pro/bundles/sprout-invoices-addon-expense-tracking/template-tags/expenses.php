<?php


function si_get_project_expenses( $project_id = 0 ) {
	$project = SI_Project::get_instance( $project_id );
	$expenses = $project->get_associated_expenses();
	return $expenses;
}

function si_get_project_expenses_totals( $project_id = 0 ) {
	$expenses = si_get_project_expenses( $project_id );
	$data = array(
			'total' => 0,
			'total_billed' => 0,
			'total_not_invoiced' => 0,
		);

	foreach ( $expenses as $expense_id ) {
		$expense = SI_Expense::get_expense_entry( $expense_id );
		if ( ! is_a( $expense, 'SI_Record' ) ) {
			continue;
		}
		$category = SI_Expense::get_instance( $expense->get_associate_id() );

		$ex_data = $expense->get_data();
		$data['total'] += (float) $ex_data['expense_val'];

		if ( isset( $ex_data['invoice_id'] ) ) {
			$data['total_billed'] += (float) $ex_data['expense_val'];
		} elseif ( $category->is_billable() ) {
			$data['total_not_invoiced'] += (float) $ex_data['expense_val'];
		}
	}
	return $data;
}

function si_get_project_expenses_total( $project_id = 0 ) {
	$totals = si_get_project_expenses_totals( $project_id );
	return $totals['total'];
}

function si_get_project_expenses_total_billed( $project_id = 0 ) {
	$totals = si_get_project_expenses_totals( $project_id );
	return $totals['total_billed'];
}

function si_get_project_expenses_total_not_invoiced( $project_id = 0 ) {
	$totals = si_get_project_expenses_totals( $project_id );
	return $totals['total_not_invoiced'];
}
