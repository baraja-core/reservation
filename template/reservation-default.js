Vue.component('reservation-default', {
	template: `<div class="my-3">
	<h1>Reservation list</h1>
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
</div>`,
	data() {
		return {
			count: null,
			items: null,
			filter: {
				query: '',
			},
			paginator: {
				page: 1,
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
		}
	}
});
