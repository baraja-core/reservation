Vue.component('calendar-default', {
	template: `<cms-default>
	<div class="container-fluid mt-2">
		<div class="row">
			<div class="col">
				<h1>Reservation calendar</h1>
			</div>
			<div v-if="productList !== null" class="col-4">
				<b-form-select :options="productList" v-model="productId"></b-form-select>
			</div>
		</div>
	</div>
	<b-card>
		<template v-if="productId === null">
			<div v-if="productList === null"" class="text-center my-5">
				<b-spinner></b-spinner><br>
				Syncing...
			</div>
			<div v-else class="text-center my-5 text-secondary">
				Please select product first.
			</div>
		</template>
		<div v-if="productId !== null && calendar === null" class="text-center my-5">
			<b-spinner></b-spinner><br>
			Downloading...
		</div>
		<div v-if="productId !== null && calendar !== null" class="container-fluid">
			<div class="row">
				<div class="col text-left">
					<b-button @click="changeYear(year - 1)" size="sm">{{ year - 1 }}</b-button>
					<b-button @click="changeYear(year + 1)" size="sm">{{ year + 1 }}</b-button>
				</div>
				<div class="col text-center">
					<h2>{{ year }}</h2>
				</div>
				<div class="col text-right">
					<b-button @click="isLoading=true;sync()" size="sm">
						<template v-if="isLoading"><b-spinner small></b-spinner></template>
						<template v-else>Sync</template>
					</b-button>
				</div>
			</div>
			<div class="row mb-3">
				<div class="col">
					<div class="row">
						<div class="col">
							<h4>Seasons</h4>
						</div>
						<div class="col-3 text-right">
							<b-button variant="primary" size="sm" v-b-modal.modal-new-season>New season</b-button>
						</div>
					</div>
					<div v-if="seasons.length === 0" class="row">
						<div class="col">
							<p class="text-secondary">Season list is empty.</p>
						</div>
					</div>
					<div v-else class="row">
						<div v-for="season in seasons" class="col-2">
							<div class="card px-2 py-1">
								<b>{{ season.name }}</b>
								<div class="card px-1">
									<div class="row">
										<div class="col">
											<b>From:</b><br>
											<span style="font-size:10pt">{{ season.from }}</span>
										</div>
										<div class="col text-right">
											<b>To:</b><br>
											<span style="font-size:10pt">{{ season.to }}</span>
										</div>
									</div>
								</div>
								Price: {{ season.price }} CZK<br>
								Minimal days: {{ season.minimalDays }}
								<div class="row">
									<div class="col">
										Active:
										<b-button :variant="season.active ? 'success' : 'danger'" size="sm" class="px-2 py-0" @click="activeSeason(season.id)">
											{{ season.active ? 'YES' : 'NO' }}
										</b-button>
									</div>
									<div class="col-5 text-right">
										<b-button variant="secondary" size="sm" class="px-2 py-0" v-b-modal.modal-edit-season @click="editSeason(season.id)">Edit</b-button>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-12">
					<h4>Calendar overview</h4>
				</div>
				<div v-for="(weeks, month) in calendar" class="col-sm-3">
					<div class="card mb-3">
						<div class="text-center">{{ month }}</div>
						<table class="table table-sm table-border">
							<tr v-for="(week, weekKey) in weeks">
								<td v-for="date in week" class="text-center p-0">
									<template v-if="date.day === null">&nbsp;</template>
									<template v-else>
										<b-button
											:variant="date.reservation ? 'primary' : (date.enable ? 'light' : 'danger')"
											size="sm"
											class="p-0 m-0"
											:title="(date.reservation
												? 'Reservation exist'
												: (date.enable ? 'Available day' : 'Blocked day')) + ' (' + date.date + ')'"
											:style="'border:2px solid ' + (date.season ? 'green' : 'black') + ';width:36px !important'"
											@click="loadDate(date.date)"
											v-b-modal.modal-date
										>
										{{ date.day }}
										</b-button>
									</template>
								</td>
							</tr>
						</table>
					</div>
				</div>
			</div>
		</div>
	</b-card>
	<b-modal id="modal-date" :title="'Date detail [' + dateInfo.date + ']'" hide-footer>
		<div v-if="dateInfo.loading" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<h3>Reservation</h3>
			<template v-if="dateInfo.reservation !== null">
				<table class="table table-sm">
					<tr>
						<th>ID:</th>
						<td>
							{{ dateInfo.reservation.id }}
							<b-button
								size="sm"
								class="py-0"
								:href="link('Reservation:detail', { id: dateInfo.reservation.id })"
							>
								Show detail
							</b-button>
						</td>
					</tr>
					<tr><th>Name:</th><td>{{ dateInfo.reservation.name }}</td></tr>
					<tr><th>E-mail:</th><td>{{ dateInfo.reservation.email }}</td></tr>
					<tr><th>Phone:</th><td>{{ dateInfo.reservation.phone }}</td></tr>
					<tr><th>Price:</th><td>{{ dateInfo.reservation.price }}</td></tr>
				</table>
			</template>
			<p v-else class="text-secondary">
				Reservation does not exist.
			</p>
			<h3>Season</h3>
			<template v-if="dateInfo.season !== null">
				<table class="table table-sm">
					<tr><th>ID:</th><td>{{ dateInfo.season.id }}</td></tr>
					<tr><th>Name:</th><td>{{ dateInfo.season.name }}</td></tr>
					<tr><th>Description:</th><td>{{ dateInfo.season.description }}</td></tr>
					<tr><th>Price:</th><td>{{ dateInfo.season.price }}</td></tr>
					<tr><th>Minimal days:</th><td>{{ dateInfo.season.minimalDays }}</td></tr>
					<tr><th>Dates:</th><td>{{ dateInfo.season.dates }}</td></tr>
				</table>
			</template>
			<p v-else class="text-secondary">
				Season does not exist.
			</p>
		</template>
	</b-modal>
	<b-modal id="modal-new-season" title="Create a new season" hide-footer>
		<b-form @submit="createSeason">
			<b-form-group label="Name:" label-for="new-season-name">
				<b-form-input id="new-season-name" v-model="seasonForm.name" required></b-form-input>
			</b-form-group>
			<b-form-group label="Description:" label-for="new-season-description">
				<b-form-textarea id="new-season-description" v-model="seasonForm.description"></b-form-textarea>
			</b-form-group>
			<div class="row">
				<div class="col">
					<b-form-group label="From date:" label-for="new-season-from">
						<b-form-datepicker id="new-season-from" v-model="seasonForm.from" required></b-form-datepicker>
					</b-form-group>
				</div>
				<div class="col">
					<b-form-group label="To date:" label-for="new-season-to">
						<b-form-datepicker id="new-season-to" v-model="seasonForm.to" required></b-form-datepicker>
					</b-form-group>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<b-form-group label="Price:" label-for="new-season-price">
						<b-form-input id="new-season-price" v-model="seasonForm.price" required></b-form-input>
					</b-form-group>
				</div>
				<div class="col">
					<b-form-group label="Minimal days:" label-for="new-season-minimalDays">
						<b-form-input id="new-season-minimalDays" v-model="seasonForm.minimalDays" required></b-form-input>
					</b-form-group>
				</div>
			</div>
			<b-button variant="primary" type="submit" @click="createSeason">Create season</b-button>
		</b-form>
	</b-modal>
	<b-modal id="modal-edit-season" :title="'Edit season (' + seasonEditForm.id +  ')'" hide-footer>
		<div class="mb-3">
			This season is <b>{{ seasonEditForm.active ? 'active' : 'hidden' }}</b>.
			<b-button :variant="seasonEditForm.active ? 'success' : 'danger'" size="sm" class="px-2 py-0" @click="activeSeason(seasonEditForm.id)">
				{{ seasonEditForm.active ? 'Mark as hidden' : 'Mark as active' }}
			</b-button>
		</div>
		<b-form @submit="saveSeason">
			<b-form-group label="Name:" label-for="edit-season-name">
				<b-form-input id="edit-season-name" v-model="seasonEditForm.name" required></b-form-input>
			</b-form-group>
			<b-form-group label="Description:" label-for="edit-season-description">
				<b-form-textarea id="edit-season-description" v-model="seasonEditForm.description"></b-form-textarea>
			</b-form-group>
			<div class="row">
				<div class="col">
					<b-form-group label="From date:" label-for="edit-season-from">
						<b-form-datepicker id="edit-season-from" v-model="seasonEditForm.from" required></b-form-datepicker>
					</b-form-group>
				</div>
				<div class="col">
					<b-form-group label="To date:" label-for="edit-season-to">
						<b-form-datepicker id="edit-season-to" v-model="seasonEditForm.to" required></b-form-datepicker>
					</b-form-group>
				</div>
			</div>
			<div class="row">
				<div class="col">
					<b-form-group label="Price:" label-for="edit-season-price">
						<b-form-input id="edit-season-price" v-model="seasonEditForm.price" required></b-form-input>
					</b-form-group>
				</div>
				<div class="col">
					<b-form-group label="Minimal days:" label-for="edit-season-minimalDays">
						<b-form-input id="edit-season-minimalDays" v-model="seasonEditForm.minimalDays" required></b-form-input>
					</b-form-group>
				</div>
			</div>
			<b-form-group>
				<b-form-checkbox v-model="seasonEditForm.flush">Flush calendar changes</b-form-checkbox>
			</b-form-group>
			<div class="row">
				<div class="col">
					<b-button variant="primary" type="submit" @click="saveSeason">Save</b-button>
				</div>
				<div class="col text-right">
					<b-button variant="danger" @click="removeSeason(seasonEditForm.id)">Remove</b-button>
				</div>
			</div>
		</b-form>
	</b-modal>
</cms-default>`,
	data() {
		return {
			isLoading: true,
			productId: null,
			productList: null,
			year: null,
			calendar: null,
			seasons: null,
			dateInfo: {
				loading: true,
				date: null
			},
			seasonForm: {},
			seasonEditForm: {},
			seasonFormDefault: {
				name: 'Season',
				description: null,
				from: null,
				to: null,
				price: 0,
				minimalDays: 1
			},
			seasonEditFormDefault: {
				id: null,
				name: null,
				description: null,
				from: null,
				to: null,
				price: null,
				minimalDays: null,
				active: false,
				flush: false
			}
		}
	},
	mounted() {
		axiosApi.get('calendar/product-list').then(req => {
			this.productList = req.data.products;
		});
		this.seasonForm = this.seasonFormDefault;
		this.seasonEditForm = this.seasonEditFormDefault;
		this.sync();
	},
	methods: {
		sync() {
			if (this.productId !== null) {
				this.$nextTick(() => {
					axiosApi.get('calendar?productId=' + this.productId + '&year=' + this.year).then(req => {
						this.isLoading = false;
						let data = req.data;
						this.year = data.year;
						this.calendar = data.calendar;
						this.seasons = data.seasons;
					})
				});
			}
		},
		changeYear(year) {
			this.year = year;
			this.sync();
		},
		loadDate(date) {
			this.dateInfo.loading = true;
			axiosApi.get('calendar/detail?productId=' + this.productId + '&date=' + date).then(req => {
				this.dateInfo = req.data;
			})
		},
		createSeason(event) {
			event.preventDefault();
			axiosApi.post('reservation-season/create-season', this.seasonForm).then(req => {
				this.$bvModal.hide('modal-new-season');
				this.sync();
			}).finally(() => {
				this.seasonForm = this.seasonFormDefault;
			});
		},
		activeSeason(id) {
			axiosApi.get('reservation-season/season-set-active?id=' + id).then(req => {
				this.sync();
			})
		},
		editSeason(id) {
			axiosApi.get('reservation-season/detail?id=' + id).then(req => {
				this.seasonEditForm = req.data;
			})
		},
		saveSeason(event) {
			event.preventDefault();
			axiosApi.post('reservation-season/save', this.seasonEditForm).then(req => {
				this.$bvModal.hide('modal-edit-season');
				this.sync();
			}).finally(() => {
				this.seasonEditForm = this.seasonEditFormDefault;
			});
		},
		removeSeason(id) {
			if (!confirm('Really?')) {
				return;
			}
			axiosApi.post('reservation-season/remove', {id: id}).then(req => {
				this.$bvModal.hide('modal-edit-season');
				this.sync();
			}).finally(() => {
				this.seasonEditForm = this.seasonEditFormDefault;
			});
		}
	},
	watch: {
		productId: function() {
			this.isLoading = true;
			this.calendar = null;
			this.sync();
		}
	}
});
