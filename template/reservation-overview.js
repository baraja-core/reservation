Vue.component('reservation-overview', {
	props: ['id'],
	template: `<cms-card>
	<div v-if="item === null" class="text-center my-5">
		<b-spinner></b-spinner>
	</div>
	<template v-else>
		<div class="row">
			<div class="col-1">
				<img :src="item.customer.avatarUrl" class="w-100">
			</div>
			<div class="col-3">
				<b>Customer:</b>
				<div>
					{{ item.customer.firstName }}
					{{ item.customer.lastName }}
				</div>
				<div>
					{{ item.customer.email }}
				</div>
				<div>
					{{ item.customer.phone }}
				</div>
			</div>
			<div class="col-4">
				Number: {{ item.number }}<br>
				From: {{ item.from }}<br>
				To: {{ item.to }}
			</div>
			<div class="col">
				Status: {{ item.status }}<br>
				Price: {{ item.price }}<br>
				Created: {{ item.createDate }}
			</div>
			<div class="col text-right">
				<b-button variant="danger" @click="storno">Storno</b-button>
			</div>
		</div>
		<div class="row mt-3">
			<div class="col">
				<h4>Items:</h4>
			</div>
		</div>
		<div v-if="item.products.length === 0" class="text-center my-5 text-secondary">
			There are no items.
		</div>
		<div v-else class="row">
			<div class="col">
				<table class="table table-sm">
					<tr>
						<th>Product</th>
						<th width="150">Quantity</th>
						<th width="150"></th>
					</tr>
					<tr v-for="product in item.products">
						<td>
							<a :href="link('Product:detail', {id: product.productId})" target="_blank">{{ product.name }}</a>
						</td>
						<td>{{ product.quantity }}</td>
						<td>
							<b-button variant="danger" size="sm" @click="removeProductItem(product.id)">x</b-button>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<div class="row mt-3">
			<div class="col">
				<h4>Calendar days:</h4>
			</div>
			<div class="col-3 text-right">
				<b-button variant="secondary" size="sm" v-b-modal.modal-edit-interval>Change interval</b-button>
			</div>
		</div>
		<div class="row">
			<div v-for="date in item.dates" class="col-sm-2 mb-3">
				<div style="border:1px dotted #aaa">
					<table class="w-100">
						<tr>
							<td>
								{{ date.date }}<br>
								{{ date.season }}
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>
		<h4>Other old reservations by this customer</h4>
		<div v-if="item.otherReservationsByCustomer.length === 0" class="text-center my-5 text-secondary">
			There are no other reservations.
		</div>
		<table v-else class="table table-sm">
			<tr>
				<th>Number</th>
				<th>Price</th>
				<th>Status</th>
			</tr>
			<tr v-for="otherReservation in item.otherReservationsByCustomer">
				<td><a :href="link('Reservation:detail', {id: otherReservation.id})">{{ otherReservation.number }}</a></td>
				<td>{{ otherReservation.price }}</td>
				<td>{{ otherReservation.status }}</td>
			</tr>
		</table>
	</template>
	<b-modal id="modal-edit-interval" title="Edit date interval" hide-footer>
		<div v-if="item === null" class="text-center my-5">
			<b-spinner></b-spinner>
		</div>
		<template v-else>
			<div class="row">
				<div class="col">
					<b-form-group label="From date:" label-for="edit-interval-from">
						<b-form-datepicker id="edit-interval-from" v-model="item.fromDate" required></b-form-datepicker>
					</b-form-group>
				</div>
				<div class="col">
					<b-form-group label="To date:" label-for="edit-interval-to">
						<b-form-datepicker id="edit-interval-to" v-model="item.toDate" required></b-form-datepicker>
					</b-form-group>
				</div>
			</div>
		</template>
		<b-button variant="primary" type="submit" @click="updateDateInterval">Update</b-button>
	</b-modal>
</cms-card>`,
	data() {
		return {
			item: null
		}
	},
	created() {
		this.sync();
	},
	methods: {
		sync() {
			axiosApi.get('reservation/overview?id=' + this.id)
				.then(req => {
					this.item = req.data;
				});
		},
		updateDateInterval() {
			if (!confirm('Really? Reserved interval will be rewrited.')) {
				return;
			}
			axiosApi.post('reservation/update-interval', {
				id: this.id,
				from: this.item.fromDate,
				to: this.item.toDate
			}).then(req => {
				this.sync();
			});
		},
		storno() {
			if (!confirm('Really? Reservation will be removed from the database.')) {
				return;
			}
			axiosApi.post('reservation/remove', {id: this.id}).then(req => {
				window.location.replace(link('Reservation:default'));
			});
		},
		removeProductItem(id) {
			if (!confirm('Really? Relation between reservation and product will be removed from the database.')) {
				return;
			}
			axiosApi.post('reservation/remove-product-item', {
				id: this.id,
				productItemId: id
			}).then(req => {
				this.sync();
			});
		}
	}
});
