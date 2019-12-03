<template>
	<div id="content" class="app-notestutorial">
		<AppNavigation>
			<input v-model="search" type="text">
			<input type="file" @change="newNote">
			<ul>
				<AppNavigationItem v-for="note in filteredNotes" :key="note.id" :item="noteEntry(note)" />
			</ul>
			<label>
				YOLO Dataset:<br>
				<input id="dataset" type="text" value="yolov3">
			</label>
			<label>
				YOLO Detection <br>
				(0 = match all; .7 = default; 1 = 100% confidence matches only):<br>
				<input id="thresh"
					type="range"
					min="0"
					max="99"
					value="70"
					step="5">
			</label>
		</AppNavigation>
		<AppContent>
			<div v-if="currentNote">
				<input id="titleTextarea"
					ref="title"
					v-model="currentNote.title"
					type="text"
					:disabled="updating">

				<!--				<img width="100%" :src="'data:image/png;base64,'+ currentNote.content">-->
				<textarea id="contentTextarea"
					ref="content"
					v-model="currentNote.content"
					:disabled="updating" />
				<input type="button"
					class="primary"
					:value="t('notestutorial', 'Confirm')"
					@click="saveNote">
				<!--
                :disabled="updating || !savePossible"
                -->
			</div>
			<div v-else id="emptycontent">
				<div class="icon-file" />
				<h2>{{ t('notestutorial', 'Upload or click a pic') }}</h2>
			</div>
		</AppContent>
	</div>
</template>

<script>
import {
	AppContent,
	AppNavigation,
	AppNavigationItem
	// AppNavigationNew
} from 'nextcloud-vue'

import axios from '@nextcloud/axios'

import EXIF from 'exif-js'

export default {
	name: 'App',
	components: {
		AppContent,
		AppNavigation,
		AppNavigationItem
		// AppNavigationNew
	},
	data: function() {
		return {
			notes: [],
			currentNoteId: null,
			updating: false,
			loading: true,
			search: ''
		}
	},
	computed: {
		filteredNotes: function() {
			var self = this
			return this.notes.filter(function(note) { return note.title.toLowerCase().indexOf(self.search.toLowerCase()) >= 0 })
			// return this.customers;
		},
		/**
		 * Return the currently selected note object
		 * @returns {Object|null}
		 */
		currentNote() {
			if (this.currentNoteId === null) {
				return null
			}
			return this.notes.find((note) => note.id === this.currentNoteId)
		},
		/**
		 * Return the item object for the sidebar entry of a note
		 * @returns {Object}
		 */
		noteEntry() {
			return (note) => {
				return {
					text: note.title,
					action: () => this.openNote(note),
					classes: this.currentNoteId === note.id ? 'active' : '',
					utils: {
						actions: [
							{
								icon: note.id === -1 ? 'icon-close' : 'icon-delete',
								text: note.id === -1 ? t('settings', 'Cancel note creation') : t('settings', 'Delete note'),
								action: () => {
									if (note.id === -1) {
										this.cancelNewNote(note)
									} else {
										this.deleteNote(note)
									}
								}
							}
						]
					}
				}
			}
		},
		/**
		 * Returns true if a note is selected and its title is not empty
		 * @returns {Boolean}
		 */
		savePossible() {
			return this.currentNote && this.currentNote.title !== ''
		}
	},
	/**
	 * Fetch list of notes when the component is loaded
	 */
	async mounted() {
		try {
			// add search funct call from here
			const response = await axios.get(OC.generateUrl('/apps/notestutorial/notes'))
			this.notes = response.data
		} catch (e) {
			console.error(e)
			OCP.Toast.error(t('notestutorial', 'Could not fetch notes'))
		}
		this.loading = false
	},
	methods: {
		/**
		 * Create a new note and focus the note content field automatically
		 * @param {Object} note Note object
		 */
		openNote(note) {
			if (this.updating) return
			this.currentNoteId = note.id
			this.$nextTick(() => this.$refs.content.focus())
		},
		/**
		 * Action tiggered when clicking the save button
		 * create a new note or save
		 */
		saveNote() {
			if (this.currentNoteId === -1) {
				this.createNote(this.currentNote)
			} else {
				this.updateNote(this.currentNote)
			}
		},
		/**
		 * Create a new note and focus the note content field automatically
		 * The note is not yet saved, therefore an id of -1 is used until it
		 * has been persisted in the backend
		 * @param {Object} e object
		 */
		newNote(e) {
			let files = e.target.files || e.dataTransfer.files
			if (!files.length) return

			let file = files[0]
			var reader = new FileReader()
			reader.readAsDataURL(file)
			if (this.currentNoteId !== -1) {
				this.currentNoteId = -1
				this.notes.push({
					id: -1,
					title: document.getElementById('dataset').value + ':' + document.getElementById('thresh').value + '_',
					content: ''
				})
				this.$nextTick(() => this.$refs.title.focus())
			}
			reader.onload = function() {
				document.getElementById('contentTextarea').innerHTML = (reader.result).replace(/^data:image\/[a-z]+;base64,/, '')
				/**
				 * @param {Object} degrees object
				 * @param {Object} minutes object
				 * @param {Object} seconds object
				 * @param {Object} direction object
				 * @returns {number}
				 */
				function ConvertDMSToDD(degrees, minutes, seconds, direction) {
					var dd = degrees + (minutes / 60) + (seconds / 3600)
					if (direction === 'S' || direction === 'W') dd *= -1
					return dd
				}
				EXIF.getData(file, function() {
					var myData = this
					// Calculate latitude decimal
					var latDegree = myData.exifdata.GPSLatitude[0].numerator
					var latMinute = myData.exifdata.GPSLatitude[1].numerator
					var latSecond = myData.exifdata.GPSLatitude[2].numerator
					var latDirection = myData.exifdata.GPSLatitudeRef
					var latFinal = ConvertDMSToDD(latDegree, latMinute, latSecond, latDirection)

					// Calculate longitude decimal
					var lonDegree = myData.exifdata.GPSLongitude[0].numerator
					var lonMinute = myData.exifdata.GPSLongitude[1].numerator
					var lonSecond = myData.exifdata.GPSLongitude[2].numerator
					var lonDirection = myData.exifdata.GPSLongitudeRef
					var lonFinal = ConvertDMSToDD(lonDegree, lonMinute, lonSecond, lonDirection)

					var final = '' + latFinal + ';' + lonFinal
					alert(final)
					document.getElementById('titleTextarea').value += final
				})
			}
		},

		/**
		 * Abort creating a new note
		 */
		cancelNewNote() {
			this.notes.splice(this.notes.findIndex((note) => note.id === -1), 1)
			this.currentNoteId = null
		},
		/**
		 * Create a new note by sending the information to the server
		 * @param {Object} note Note object
		 */
		async createNote(note) {
			this.updating = true
			try {
				const response = await axios.post(OC.generateUrl(`/apps/notestutorial/notes`), note)
				const index = this.notes.findIndex((match) => match.id === this.currentNoteId)
				this.$set(this.notes, index, response.data)
				this.currentNoteId = response.data.id
			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('notestutorial', 'Could not create the note'))
			}
			this.updating = false
		},
		/**
		 * Update an existing note on the server
		 * @param {Object} note Note object
		 */
		async updateNote(note) {
			this.updating = true
			try {
				await axios.put(OC.generateUrl(`/apps/notestutorial/notes/${note.id}`), note)
			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('notestutorial', 'Could not update the note'))
			}
			this.updating = false
		},
		/**
		 * Delete a note, remove it from the frontend and show a hint
		 * @param {Object} note Note object
		 */
		async deleteNote(note) {
			try {
				await axios.delete(OC.generateUrl(`/apps/notestutorial/notes/${note.id}`))
				this.notes.splice(this.notes.indexOf(note), 1)
				if (this.currentNoteId === note.id) {
					this.currentNoteId = null
				}
				OCP.Toast.success(t('notestutorial', 'Note deleted'))
			} catch (e) {
				console.error(e)
				OCP.Toast.error(t('notestutorial', 'Could not delete the note'))
			}
		}
	}
}
</script>
<style scoped>
	#app-content > div {
		width: 100%;
		height: 100%;
		padding: 20px;
		display: flex;
		flex-direction: column;
		flex-grow: 1;
	}
	input[type="text"] {
		width: 100%;
	}
	textarea {
		flex-grow: 1;
		width: 100%;
	}
</style>
