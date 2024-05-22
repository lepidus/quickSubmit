/**
 * @file cypress/tests/functional/QuickSubmit.cy.js
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file LICENSE.
 *
 */

describe('Quick Submit plugin tests', function() {
	it('Creates a published quick submission', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.get('.app__nav a:contains("Tools")').click();
		cy.get('a:contains("QuickSubmit Plugin")').click();
		cy.get('select[id="seriesId"]').select('Library & Information Studies');
		cy.waitJQuery(); // Wait for form resubmission hack on section change.
		cy.wait(2000); // FIXME: Detached element delay

		cy.get('input[id^="title-en-"]').type('QuickSubmit Published Test Submission', {delay: 0});
		cy.get('textarea[id^="abstract-en-"]').then(node => {
			cy.setTinyMceContent(node.attr('id'), 'This is a published QuickSubmit test submission.');
		});

		// Add an author
		cy.get('a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]').click({force: true});
		cy.wait(1000); // Form init delay
		cy.get('input[id^="givenName-en-"]').type('Quincy', {delay: 0});
		cy.get('input[id^="familyName-en-"]').type('Submitter', {delay: 0});
		cy.get('select[id="country"]').select('Canada');
		cy.get('input[id^=email-]').type('qsubmitter@mailinator.com', {delay: 0});
		cy.get('input[id^="affiliation-en-"]').type('Queens University', {delay: 0});
		cy.get('label:contains("Author")').click({multiple: true, force: true});
		cy.get('form[id="editAuthor"] button:contains("Save")').click();
		cy.get('div:contains("Author added.")');

		// Add a publication format
		cy.get('a[id^="component-grid-catalogentry-publicationformatgrid-addFormat-button-"]').click();
		cy.wait(1000); // Wait for the form to settle
		cy.get('input[id^=name-en-]').type('PDF', {delay: 0});
		cy.get('form#addPublicationFormatForm button:contains("OK")').click();
		cy.get('a:contains("Change File")').click();
		cy.wait(1000); // Wait for the form to settle
		cy.get('select[id="genreId"]').select('Book Manuscript');
		cy.fixture('dummy.pdf', 'base64').then(fileContent => {
			cy.get('div[id^="fileUploadWizard"] input[type=file]').attachFile(
				{fileContent, 'filePath': 'book.pdf', 'mimeType': 'application/pdf', 'encoding': 'base64'}
			);
		});
		cy.get('button').contains('Continue').click();
		cy.get('button').contains('Continue').click();
		cy.get('button').contains('Complete').click();
		cy.wait(1000);
		cy.get('a:contains("Not Available")').click();
		cy.get('.pkpModalConfirmButton').click();
		cy.wait(1000);
		cy.get('a:contains("Set Terms")').click();
		cy.get('label:contains(Open Access) input').check();
		cy.get('form#approvedProofForm button:contains("Save")').click();

		// Add a chapter
		cy.get('a[id^="component-grid-users-chapter-chaptergrid-addChapter-button-"]').click();
		cy.wait(1000); // Wait for the form to settle
		cy.get('form[id="editChapterForm"] input[id^=title-en-]').type('QuickSubmit Published Test Chapter', {delay: 0});
		cy.get('label:contains(Quincy Submitter) input').check();
		cy.get('label:contains(book.pdf) input').check();
		cy.get('form[id="editChapterForm"] button:contains("Save")').click();

		// Schedule for publication
		cy.get('input#submissionPublished').click();
		cy.get('input[id^="datePublished-"]:visible').type('2020-01-01', {delay: 0});
		cy.get('input[id^="licenseUrl-"]').click({force: true}); // Take focus out of datepicker

		// Complete the submission
		cy.get('form[id="quickSubmitForm"] button:contains("Save")').click();
		cy.waitJQuery();

		// Test the submission in the published front end
		cy.get('.app__contextTitle:contains("Public Knowledge Press")').click();
		cy.get('a:contains("Catalog")').click();
		cy.get('a:contains("QuickSubmit Published Test Submission")').click();
		cy.get('div.abstract p:contains("This is a published QuickSubmit test submission.")');
		cy.get('div.files a:contains("PDF")').click();
		cy.get('iframe');
	});

	it('Creates an unpublished quick submission', function() {
		cy.login('admin', 'admin', 'publicknowledge');

		cy.get('.app__nav a:contains("Tools")').click();
		cy.get('a:contains("QuickSubmit Plugin")').click();
		cy.waitJQuery(); // Wait for form resubmission hack on section change.
		cy.wait(2000); // FIXME: Detached element delay

		cy.get('input[id^="title-en-"]').type('QuickSubmit Unpublished Test Submission', {delay: 0});
		cy.get('textarea[id^="abstract-en-"]').then(node => {
			cy.setTinyMceContent(node.attr('id'), 'This is an unpublished QuickSubmit test submission.');
		});

		// Add an author
		cy.get('a[id^="component-grid-users-author-authorgrid-addAuthor-button-"]').click({force: true});
		cy.wait(1000); // Form init delay
		cy.get('input[id^="givenName-en-"]').type('Quincy', {delay: 0});
		cy.get('input[id^="familyName-en-"]').type('Submitter', {delay: 0});
		cy.get('select[id="country"]').select('Canada');
		cy.get('input[id^=email-]').type('qsubmitter@mailinator.com', {delay: 0});
		cy.get('input[id^="affiliation-en-"]').type('Queens University', {delay: 0});
		cy.get('label:contains("Author")').click({multiple: true, force: true});
		cy.get('form[id="editAuthor"] button:contains("Save")').click();
		cy.get('div:contains("Author added.")');

		// Complete the submission
		cy.get('form[id="quickSubmitForm"] button:contains("Save")').click();
		cy.get('a:contains("Go to Submission")').click();
		cy.get('button:contains("Schedule For Publication")');
	});
})
