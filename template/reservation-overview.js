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
		</div>
		<h4 class="mt-3">Calendar days:</h4>
		<div class="row">
			<div v-for="date in item.dates" class="col-sm-2 mb-3">
				<div style="border:1px dotted #aaa">
					<table class="w-100">
						<tr>
							<td>
								{{ date.date }}<br>
								{{ date.season }}
							</td>
							<td class="text-right">
								<b-button variant="danger" size="sm" class="px-1 py-0" @click="removeDate(date.id)">x</b-button>
							</td>
						</tr>
					</table>
				</div>
			</div>
		</div>
	</template>
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
		removeDate(id) {
			if (!confirm('Really?')) {
				return;
			}
			this.item = null;
		}
	}
});
