Vue.component('reservation-default', {
	template: `<cms-default>
	<div class="row">
		<div class="col">
			<h1>Reservation list</h1>
		</div>
		<div class="col-3 text-right">
			<b-button size="sm" variant="secondary" v-b-modal.modal-configuration>Configuration</b-button>
		</div>
	</div>
	<b-card>
		<table class="table table-sm">
			<tr>
				<th>Number</th>
				<th>Created</th>
				<th>Customer</th>
				<th>From</th>
				<th>To</th>
				<th>Statuc</th>
				<th>Price</th>
			</tr>
			<tr v-for="item in items">
				<td>
					<a :href="link('Reservation:detail', {id: item.id})">{{ item.number }}</a>
				</td>
				<td>{{ item.createDate }}</td>
				<td>
					<div>{{ item.customer.firstName }} {{ item.customer.lastName }}</div>
					<div>
						{{ item.customer.email }}<br>
						{{ item.customer.phone }}
					</div>
				</td>
				<td>{{ item.from }}</td>
				<td>{{ item.to }}</td>
				<td>{{ item.status }}</td>
				<td>{{ item.price }}</td>
			</tr>
		</table>
	</b-card>
	<b-modal id="modal-configuration" title="Configuration" @shown="syncConfiguration" hide-footer>
		<b-form-group label="Notification send to:" label-for="configuration-to">
			<b-form-input id="configuration-to" v-model="configuration.to" required></b-form-input>
		</b-form-group>
		<b-form-group label="Notification send copy (can be empty):" label-for="configuration-copy">
			<b-form-input id="configuration-copy" v-model="configuration.copy" required></b-form-input>
		</b-form-group>
		<b-form-group label="Notification subject prefix:" label-for="configuration-subject">
			<b-form-input id="configuration-subject" v-model="configuration.subject" required></b-form-input>
		</b-form-group>
		<b-button variant="primary" @click="saveConfiguration">Save</b-button>
	</b-modal>
</cms-default>`,
	data() {
		return {
			count: null,
			items: null,
			filter: {
				query: '',
			},
			paginator: {
				page: 1,
			},
			configuration: {
				to: '',
				copy: '',
				subject: ''
			}
		}
	},
	created() {
		this.sync();
	},
	methods: {
		sync() {
			let query = {
				query: this.filter.query ? this.filter.query : null,
				page: this.paginator.page
			};
			axiosApi.get('reservation?' + httpBuildQuery(query))
				.then(req => {
					this.count = req.data.count;
					this.items = req.data.items;
					this.paginator = req.data.paginator;
				});
		},
		syncConfiguration() {
			axiosApi.get('reservation/notification-configuration')
				.then(req => {
					this.configuration = req.data;
				});
		},
		saveConfiguration() {
			axiosApi.post('reservation/notification-configuration', this.configuration).then(req => {
				this.$bvModal.hide('modal-configuration');
			});
		}
	}
});
