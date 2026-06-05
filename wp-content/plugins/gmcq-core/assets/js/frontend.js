jQuery(function ($) {
	var attemptId = 0;
	var startTime = Date.now();
	var cur = 0;

	function showQ(i) {
		$('.gmcq-question').hide().eq(i).show();
		if (i >= $('.gmcq-question').length - 1) {
			$('#gmcq-submit-quiz').show();
		}
	}

	$('#gmcq-start-btn').on('click', function () {
		var quizId = $('.gmcq-quiz-wrap').data('quiz-id');
		$.post(
			gmcqPublic.ajaxUrl,
			{
				action: 'gmcq_start_attempt',
				quiz_id: quizId,
				_ajax_nonce: gmcqPublic.nonce,
			},
			function (r) {
				if (r.success) {
					attemptId = r.data.attempt_id;
					startTime = Date.now();
					$('#gmcq-quiz-start').hide();
					$('#gmcq-quiz-questions').show();
				} else {
					alert(r.data.message || 'Error');
				}
			}
		);
	});

	function saveAnswer(skip) {
		var $q = $('.gmcq-question').eq(cur);
		var qid = $q.data('question-id');
		var ids = skip
			? []
			: $q
					.find('input:checked')
					.map(function () {
						return parseInt(this.value, 10);
					})
					.get();

		$.post(
			gmcqPublic.ajaxUrl,
			{
				action: 'gmcq_submit_answer',
				attempt_id: attemptId,
				question_id: qid,
				answer_ids: ids,
				time_spent: 0,
				_ajax_nonce: gmcqPublic.nonce,
			},
			function () {
				cur++;
				if (cur < $('.gmcq-question').length) {
					showQ(cur);
				} else {
					$('#gmcq-submit-quiz').show();
				}
			}
		);
	}

	$('.gmcq-quiz-wrap').on('click', '.gmcq-save-answer', function () {
		saveAnswer(false);
	});

	$('.gmcq-quiz-wrap').on('click', '.gmcq-skip', function () {
		saveAnswer(true);
	});

	$('#gmcq-submit-quiz').on('click', function () {
		var timeTaken = Math.floor((Date.now() - startTime) / 1000);
		$.post(
			gmcqPublic.ajaxUrl,
			{
				action: 'gmcq_complete_attempt',
				attempt_id: attemptId,
				time_taken: timeTaken,
				_ajax_nonce: gmcqPublic.nonce,
			},
			function (r) {
				if (r.success) {
					var a = r.data.attempt;
					$('#gmcq-quiz-questions').hide();
					$('#gmcq-quiz-results')
						.html(
							'<h3>Score: ' +
								a.percentage +
								'%</h3><p>' +
								(a.passed ? 'Pass' : 'Fail') +
								'</p>'
						)
						.show();
				}
			}
		);
	});
});
