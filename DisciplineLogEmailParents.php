<?php
/**
 * Email Discipline Log to Parents
 *
 * @package Email module
 */

require_once 'modules/Discipline/includes/ReferralLog.fnc.php';
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
		SaveTemplate( $_REQUEST['inputdisciplinelogemailtext'] );

		$message = str_replace( "''", "'", $_REQUEST['inputdisciplinelogemailtext'] );

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

		$extra['WHERE'] = " AND s.STUDENT_ID IN (" . $st_list . ")";

		$student_RET = GetStuList( $extra );

		// echo '<pre>'; var_dump($student_RET); echo '</pre>';

		// Generate and get Discipline Logs.
		$referral_logs = ReferralLogsGenerate( $extra );

		$error_email_list = array();

		$pdf_options = array(
			'css' => true,
			'margins' => array(),
			'mode' => 3, // Save.
		);

		foreach ( (array) $student_RET as $student_id => $student )
		{
			$to = $student['PARENT_EMAIL'];

			$reply_to = $cc = null;

			if ( filter_var( User( 'EMAIL' ), FILTER_VALIDATE_EMAIL ) )
			{
				$reply_to = User( 'NAME' ) . ' <' . User( 'EMAIL' ) . '>';
			}

			$subject = _( 'Discpline Log' ) .
				' - ' . $student['FULL_NAME'];

			// Substitutions.
			$msg = str_replace(
				array(
					'__FIRST_NAME__',
					'__LAST_NAME__',
					'__SCHOOL_ID__',
					'__PARENT_NAME__',
				),
				array(
					$student['FIRST_NAME'],
					$student['LAST_NAME'],
					SchoolInfo( 'TITLE' ),
					$student['PARENT_NAME'],
				),
				$message
			);

			if ( isset( $referral_logs[ $student_id ] ) )
			{
				// Generate PDF.
				$handle = PDFStart( $pdf_options );

				echo $referral_logs[ $student_id ];

				$pdf_file = PDFStop( $handle );

				$pdf_name = $subject . '.pdf';

				// Send Email.
				$result = SendEmail(
					$to,
					$subject,
					$msg,
					$reply_to,
					$cc,
					array( array( $pdf_file, $pdf_name ) )
				);

				// Delete PDF file.
				unlink( $pdf_file );

				if ( ! $result )
					$error_email_list[] = $student['PARENT_NAME'] . ' (' . $student['PARENT_EMAIL'] . ')';
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

		if ( empty( $referral_logs ) )
		{
			$error[] = _( 'No Students were found.' );
		}
		else
			$note[] = dgettext( 'Email', 'The discpline logs have been sent.' );
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
		echo '<form action="' . PreparePHP_SELF(
			$_REQUEST,
			array( 'search_modfunc' ),
			array( 'modfunc' => 'save' )
		) . '" method="POST">';

		$extra['header_right'] = SubmitButton( dgettext( 'Email', 'Send Log to Selected Parents' ) );

		$extra['extra_header_left'] = '<table>';

		$template = GetTemplate();

		// Email Template Textarea.
		$extra['extra_header_left'] .= '<tr class="st"><td>
			<label><textarea name="inputdisciplinelogemailtext" cols="97" rows="5">' . $template . '</textarea>
			<span class="legend-gray">' . _( 'Discpline Log' ) . ' - ' . _( 'Email Text' ) . '</span></label>
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

		$extra['extra_header_left'] .= '</tr></table>
			<span class="legend-gray">' . _( 'Substitutions' ) . '</span></td></tr>';

		$extra['extra_header_left'] .= '</table>';

		// Add Include in Discipline Log form.
		$extra['extra_header_left'] .= '<table>' . ReferralLogIncludeForm() . '</table>';

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

	// SELECT Number of Referrals.
	$extra['SELECT'] .= ',(SELECT COUNT(dr.ID)
		FROM DISCIPLINE_REFERRALS dr
		WHERE dr.STUDENT_ID=ssm.STUDENT_ID
		AND dr.SYEAR=ssm.SYEAR) AS REFERRALS';

	// ORDER BY Name.
	$extra['ORDER_BY'] = 'REFERRALS, FULL_NAME';

	// Call functions to format Columns.
	$extra['functions'] = array( 'CHECKBOX' => '_makeChooseCheckbox' );

	// Columns Titles.
	$extra['columns_before'] = array(
		'CHECKBOX' => '</a><input type="checkbox" value="Y" name="controller" onclick="checkAll(this.form,this.form.controller.checked,\'student\');" /><A>',
	);

	$extra['columns_after'] = array(
		'REFERRALS' => _( 'Number of Referrals' ),
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
			SubmitButton( dgettext( 'Email', 'Send Log to Selected Parents' ) ) . '</div>';
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
 * @return string Checkbox or empty string if no Email or no Referrals
 */
function _makeChooseCheckbox( $value, $column )
{
	global $THIS_RET;

	// If valid email & has Referrals.
	if ( filter_var( $THIS_RET['PARENT_EMAIL'], FILTER_VALIDATE_EMAIL )
		&& intval( $THIS_RET['REFERRALS'] ) > 0 )
	{
		return '<input type="checkbox" name="student[' . $value . ']" value="' . $value . '" />';
	}
	else
		return '';
}
