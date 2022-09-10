<?php
/**
 * Admin hours list
 *
 * @package ZeroSpam
 */

$data = array(
	'12am' => 0,
	'1am'  => 0,
	'2am'  => 0,
	'3am'  => 0,
	'4am'  => 0,
	'5am'  => 0,
	'6am'  => 0,
	'7am'  => 0,
	'8am'  => 0,
	'9am'  => 0,
	'10am' => 0,
	'11am' => 0,
	'12pm' => 0,
	'1pm'  => 0,
	'2pm'  => 0,
	'3pm'  => 0,
	'4pm'  => 0,
	'5pm'  => 0,
	'6pm'  => 0,
	'7pm'  => 0,
	'8pm'  => 0,
	'9pm'  => 0,
	'10pm' => 0,
	'11pm' => 0,
);
foreach ( $entries as $key => $entry ) {
	$entry_date_key = gmdate( 'ga', strtotime( $entry['date_recorded'] ) );
	$data[ $entry_date_key ]++;
}

arsort( $data );
array_splice( $data, 5 );
?>
<h3><?php _e( 'All-time Detections by Hour', 'zero-spam' ); ?></h3>
<ul class="zerospam-list zerospam-list--top">
	<?php foreach ( $data as $time => $count ) : ?>
	<li class="zerospam-list__item">
		<span class="zerospam-list__value zerospam-list__value--label">
			<?php echo $time; ?>
		</span>
		<span class="zerospam-list__value zerospam-list__value--count"><?php echo number_format( $count, 0 ); ?></span>
	</li>
<?php endforeach; ?>
</ul>
