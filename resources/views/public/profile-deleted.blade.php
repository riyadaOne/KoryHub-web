{{-- S-08 pour un compte supprimé : le nom @auteur reste réservé, rien d'autre n'est montré. --}}
<x-layouts::public :title="'@'.$handle">
    <section class="py-12">
        <x-empty-state icon="user-minus" :title="__('discover.profile_deleted', ['handle' => '@'.$handle])" data-test="profile-deleted" />
    </section>
</x-layouts::public>
