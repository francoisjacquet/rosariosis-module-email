<?php
/**
 * Email Studnt Balances to Parents
 *
 * @package Email module
 */

require_once 'ProgramFunctions/SendEmail.fnc.php';

if ( file_exists( 'ProgramFunctions/Template.fnc.php' ) )
{
	// @since 3.6.
	require_once 'ProgramFunctions/Template.fnc.php';
}
else
{
	// @deprecated.
	require_once 'modules/Email/includes/Template.fnc.php';
}

DrawHeader( ProgramTitle() );

// Send emails.
if ( isset( $_REQUEST['modfunc'] )
	&& $_REQUEST['modfunc'] === 'save'
	&& AllowEdit() )
{
	if ( isset( $_POST['student'] ) )
	{
		SaveTemplate( $_REQUEST['inputstudentbalancesemailtext'] );

		$message = str_replace( "''", "'", $_REQUEST['inputstudentbalancesemailtext'] );

		$st_list = '\'' . implode( '\',\'', $_REQUEST['student'] ) . '\'';

		// SELECT Staff details.
		$extra['SELECT'] .= ",(SELECT st.FIRST_NAME||' '||st.LAST_NAME
			FROM STAFF st,STUDENTS_JOIN_USERS sju
			WHERE sju.STAFF_ID=st.STAFF_ID
			AND s.STUDENT_ID=sju.STUDENT_ID
			AND st.SYEAR='" . UserSyear() . "' LIMIT 1) AS PARENT_NAME";

		$extra['SELECT'] .= ",(SELECT st.EMAIL
			FROM STAFF st,STUDENTS_JOIN_USERS sju
			WHERE sju.STAFF_ID=st.STAFF_ID
			AND s.STUDENT_ID=sju.STUDENT_ID
			AND st.SYEAR='" . UserSyear() . "' LIMIT 1) AS PARENT_EMAIL";

		// SELECT Student Balance.
		$extra['SELECT'] .= ',(COALESCE((SELECT SUM(f.AMOUNT)
				FROM BILLING_FEES f
				WHERE f.STUDENT_ID=ssm.STUDENT_ID
				AND f.SYEAR=ssm.SYEAR),0)
			-COALESCE((SELECT SUM(p.AMOUNT)
				FROM BILLING_PAYMENTS p
				WHERE p.STUDENT_ID=ssm.STUDENT_ID
				AND p.SYEAR=ssm.SYEAR),0)) AS BALANCE';

		$extra['WHERE'] = " AND s.STUDENT_ID IN (" . $st_list . ")";

		// Call functions to format Columns.
		$extra['functions'] = array( 'BALANCE' => '_makeCurrency' );

		$student_RET = GetStuList( $extra );

		// echo '<pre>'; var_dump($student_RET); echo '</pre>';

		$error_email_list = array();

		foreach ( (array) $student_RET as $student )
		{
			$to = $student['PARENT_EMAIL'];

			$reply_to = null;

			if ( filter_var( User( 'EMAIL' ), FILTER_VALIDATE_EMAIL ) )
			{
				$reply_to = User( 'NAME' ) . ' <' . User( 'EMAIL' ) . '>';
			}

			$subject = dgettext( 'Email', 'Student Balance' ) .
				' - ' . $student['FIRST_NAME'] . ' ' . $student['LAST_NAME'];

			// Substitutions.
			$msg = str_replace(
				array(
					'__FIRST_NAME__',
					'__LAST_NAME__',
					'__SCHOOL_ID__',
					'__PARENT_NAME__',
					'__BALANCE__',
				),
				array(
					$student['FIRST_NAME'],
					$student['LAST_NAME'],
					SchoolInfo( 'TITLE' ),
					$student['PARENT_NAME'],
					strip_tags( $student['BALANCE'] ), // Remove <!-- 0 --> comment.
				),
				$message
			);

			$result = SendEmail( $to, $subject, $msg, $reply_to );

			if ( ! $result )
			{
				$error_email_list[] = $student['PARENT_NAME'] .
					' (' . $student['PARENT_EMAIL'] . ')';
			}
		}

		if ( ! empty( $error_email_list ) )
		{
			$error_email_list = implode( ', ', $error_email_list );

			$error[] = sprintf(
				dgettext( 'Email', 'Email not sent to: %s' ),
				$error_email_list
			);
		}

		$note[] = dgettext( 'Email', 'The student balances have been sent.' );
	}
	// No Users selected.
	else
		$error[] = _( 'You must choose at least one student.' );

	unset( $_SESSION['_REQUEST_vars']['modfunc'] );

	unset( $_REQUEST['modfunc'] );
}

// Display errors if any.
if ( isset( $error ) )
{
	echo ErrorMessage( $error );
}

// Display notes if any.
if ( isset( $note ) )
{
	echo ErrorMessage( $note, 'note' );
}

// Display Search screen or Student list.
if ( empty( $_REQUEST['modfunc'] )
	|| $_REQUEST['search_modfunc'] === 'list' )
{
	// Open Form & Display Email options.
	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<form action="Modules.php?modname=' . $_REQUEST['modname'] . '&modfunc=save" method="POST">';

		$extra['header_right'] = SubmitButton( dgettext( 'Email', 'Send Balances to Selected Parents' ) );

		$extra['extra_header_left'] = '<table>';

		$template = GetTemplate();

		// Email Template Textarea.
		$extra['extra_header_left'] .= '<tr class="st"><td>
			<label><textarea name="inputstudentbalancesemailtext" cols="97" rows="5">' . $template . '</textarea>
			<span class="legend-gray">' . dgettext( 'Email', 'Student Balance' ) . ' - ' . _( 'Email Text' ) . '</span></label>
			</td></tr>';

		// Spacing.
		$extra['extra_header_left'] .= '<tr><td>&nbsp;</td></tr>';

		// Substitutions.
		$extra['extra_header_left'] .= '<tr class="st">
			<td><table><tr class="st">';

		$extra['extra_header_left'] .= '<td>__PARENT_NAME__</td>
			<td>= ' . _( 'Parent Name' ) . '</td>
			<td colspan="3">&nbsp;</td>';

		$extra['extra_header_left'] .= '</tr><tr class="st">';

		$extra['extra_header_left'] .= '<td>__FIRST_NAME__</td>
			<td>= ' . _( 'First Name' ) . '</td><td>&nbsp;</td>';

		$extra['extra_header_left'] .= '<td>__LAST_NAME__</td>
			<td>= ' . _( 'Last Name' ) . '</td>';

		$extra['extra_header_left'] .= '</tr><tr class="st">';

		$extra['extra_header_left'] .= '<td>__SCHOOL_ID__</td>
			<td>= ' . _( 'School' ) . '</td>
			<td>&nbsp;</td>';

		$extra['extra_header_left'] .= '<td>__BALANCE__</td>
			<td>= ' . dgettext( 'Email', 'Student Balance' ) . '</td>';

		$extra['extra_header_left'] .= '</tr></table>
			<span class="legend-gray">' . _( 'Substitutions' ) . '</span></td></tr>';

		$extra['extra_header_left'] .= '</table>';
	}

	$extra['SELECT'] = ",s.STUDENT_ID AS CHECKBOX";

	// SELECT Staff details.
	$extra['SELECT'] .= ",(SELECT st.FIRST_NAME||' '||st.LAST_NAME
		FROM STAFF st,STUDENTS_JOIN_USERS sju
		WHERE sju.STAFF_ID=st.STAFF_ID
		AND s.STUDENT_ID=sju.STUDENT_ID
		AND st.SYEAR='" . UserSyear() . "' LIMIT 1) AS PARENT_NAME";

	$extra['SELECT'] .= ",(SELECT st.EMAIL
		FROM STAFF st,STUDENTS_JOIN_USERS sju
		WHERE sju.STAFF_ID=st.STAFF_ID
		AND s.STUDENT_ID=sju.STUDENT_ID
		AND st.SYEAR='" . UserSyear() . "' LIMIT 1) AS PARENT_EMAIL";

	// SELECT Student Balance.
	$extra['SELECT'] .= ',(COALESCE((SELECT SUM(f.AMOUNT)
			FROM BILLING_FEES f
			WHERE f.STUDENT_ID=ssm.STUDENT_ID
			AND f.SYEAR=ssm.SYEAR),0)
		-COALESCE((SELECT SUM(p.AMOUNT)
			FROM BILLING_PAYMENTS p
			WHERE p.STUDENT_ID=ssm.STUDENT_ID
			AND p.SYEAR=ssm.SYEAR),0)) AS BALANCE';

	// ORDER BY Balance, Name.
	$extra['ORDER_BY'] = 'BALANCE DESC, FULL_NAME';

	// Call functions to format Columns.
	$extra['functions'] = array( 'CHECKBOX' => '_makeChooseCheckbox', 'BALANCE' => '_makeCurrency' );

	// Columns Titles.
	$extra['columns_before'] = array(
		'CHECKBOX' => '</a><input type="checkbox" value="Y" name="controller" onclick="checkAll(this.form,this.form.controller.checked,\'student\');" /><A>',
	);

	$extra['columns_after'] = array(
		'BALANCE' => _( 'Balance' ),
		'PARENT_NAME' => _( 'Parent Name' ),
		'PARENT_EMAIL' => _( 'Email' ),
	);

	// No link for Student's name.
	$extra['link'] = array( 'FULL_NAME' => false );

	// Remove Current Student if any.
	$extra['new'] = true;

	// Display Search screen or Search Students.
	Search( 'student_id', $extra );

	// Submit & Close Form.
	if ( $_REQUEST['search_modfunc'] === 'list' )
	{
		echo '<br /><div class="center">' .
			SubmitButton( dgettext( 'Email', 'Send Balances to Selected Parents' ) ) . '</div>';
		echo '</form>';
	}
}


/**
 * Make Choose Checkbox
 *
 * Local function
 *
 * @param  string $value  STUDENT_ID value.
 * @param  string $column 'CHECKBOX'.
 *
 * @return string Checkbox or empty string if no Email
 */
function _makeChooseCheckbox( $value, $column )
{
	global $THIS_RET;

	// If valid email.
	if ( filter_var( $THIS_RET['PARENT_EMAIL'], FILTER_VALIDATE_EMAIL ) )
	{
		return '<input type="checkbox" name="student[' . $value . ']" value="' . $value . '" />';
	}
	else
		return '';
}


/**
 * Make Currency
 *
 * Local function
 *
 * @param  string $value  Balance value.
 * @param  string $column 'BALANCE'.
 *
 * @return string Formatted Balance value with Currency
 */
function _makeCurrency( $value, $column )
{
	return Currency( $value * -1 );
}
